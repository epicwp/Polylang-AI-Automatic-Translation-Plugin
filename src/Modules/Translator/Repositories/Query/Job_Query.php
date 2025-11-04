<?php
namespace PLLAT\Translator\Repositories\Query;

use Mehedi\WPQueryBuilder\DB;
use Mehedi\WPQueryBuilder\Query\Builder;
use PLLAT\Translator\Enums\RunStatus;
use PLLAT\Translator\Models\Job;
use PLLAT\Translator\Models\Run;

/**
 * A query builder for the job table.
 */
class Job_Query {
	/**
	 * The query builder.
	 *
	 * @var Builder
	 */
	private Builder $query;

	/**
	 * The output format ARRAY_A or ARRAY_N or OBJECT.
	 *
	 * @var string
	 */
	private string $output_format = ARRAY_A;

	/**
	 * Composable OR filters and join requirements for run-related conditions.
	 *
	 * @var array<int, callable(Builder,bool):void>
	 */
	private array $or_group_filters = array();

	/**
	 * Whether the runs join is required.
	 *
	 * @var bool
	 */
	private bool $requires_runs_join = false;

	/**
	 * Whether the OR filters have been applied.
	 *
	 * @var bool
	 */
	private bool $or_filters_applied = false;

	/**
	 * Create a new job query.
	 *
	 * @param string $type The type of the job (post or term).
	 */
	public function __construct( string $type ) {
		$this->query = DB::table( Job::TABLE_NAME );
		$this->query->where( 'type', $type );
	}

	/**
	 * Set the language from.
	 *
	 * @param string $lang_from The language from.
	 * @return self
	 */
	public function set_lang_from( string $lang_from ): self {
		$this->query->where( 'lang_from', $lang_from );
		return $this;
	}

	/**
	 * Set the languages to.
	 *
	 * @param array $langs_to The languages to.
	 * @return self
	 */
	public function set_langs_to( array $langs_to ): self {
		$this->query->whereIn( 'lang_to', $langs_to );
		return $this;
	}

	/**
	 * Set the statuses.
	 *
	 * @param array $statuses The statuses.
	 * @return self
	 */
	public function set_statuses( array $statuses ): self {
		$this->query->whereIn( Job::TABLE_NAME . '.status', $statuses );
		return $this;
	}

	/**
	 * Set the IDs from (for specific ID filtering).
	 *
	 * @param array $ids_from The specific source content IDs.
	 * @return self
	 */
	public function set_ids_from( array $ids_from ): self {
		if ( \count( $ids_from ) > 0 ) {
			$this->query->whereIn( 'id_from', $ids_from );
		}
		return $this;
	}

	/**
	 * Filter op content types (post_type of taxonomy) via jobs.content_type column.
	 * Replaces need for JOINs with wp_posts/wp_term_taxonomy.
	 *
	 * @param array $content_types The content types (post types or taxonomies).
	 * @return self
	 */
	public function set_content_types( array $content_types ): self {
		if ( \count( $content_types ) > 0 ) {
			$this->query->whereIn( 'content_type', $content_types );
		}
		return $this;
	}

	/**
	 * Set the selected columns.
	 *
	 * @param array $cols The columns.
	 * @return self
	 */
	public function select( array $cols ): self {
		$this->query->select( $cols );
		return $this;
	}

	/**
	 * Set the run ID.
	 *
	 * @param int $run_id The run ID.
	 * @return self
	 */
	public function set_run_id( int $run_id ): self {
		$this->query->where( 'run_id', $run_id );
		return $this;
	}

	/**
	 * Set the output format.
	 *
	 * @param string $output The output format.
	 * @return self
	 */
	public function set_output_format( string $output ): self {
		$this->output_format = $output;
		return $this;
	}

	/**
	 * Get the query.
	 *
	 * @return string The query.
	 */
	public function get(): string {
		$this->apply_run_inclusion_filters();
		return $this->query->toSql();
	}

	/**
	 * Execute the built query and return results via the builder.
	 *
	 * @return array
	 */
	public function fetch(): array {
		$this->apply_run_inclusion_filters();
		$results = $this->query->get();
		return $this->guarantee_output_format( $results );
	}

	/**
	 * Get the parameter bindings for this query.
	 *
	 * @return array<int, mixed>
	 */
	public function get_bindings(): array {
		return \method_exists( $this->query, 'getBindings' ) ? $this->query->getBindings() : array();
	}

	/**
	 * Get the output format.
	 *
	 * @return string The output format.
	 */
	public function get_output_format(): string {
		return $this->output_format;
	}

	/**
	 * Set the query to fetch jobs from orphaned runs (without a run id).
	 *
	 * @return self
	 */
	public function include_orphaned(): self {
		$this->add_or_filter(
			static function ( Builder $q, bool $is_first ): void {
				if ( $is_first ) {
					$q->where( Job::TABLE_NAME . '.run_id', null );
				} else {
					$q->orWhere( Job::TABLE_NAME . '.run_id', null );
				}
			},
			false,
		);
		return $this;
	}

	/**
	 * Set the query to fetch jobs from non-running runs.
	 *
	 * @return self
	 */
	public function include_from_inactive_runs(): self {
		// runs.status IN (inactive but not completed)
		$inactive = \array_map(
			static fn( $s ) => \is_string( $s ) ? $s : $s->value,
			RunStatus::getInactiveButNotCompleted(),
		);
		$this->add_or_filter(
			static function ( Builder $q, bool $is_first ) use ( $inactive ): void {
				// whereIn accepts an operator; use 'and' for first, 'or' for next
				$operator = $is_first ? 'and' : 'or';
				$q->whereIn( Run::TABLE_NAME . '.status', $inactive, $operator );
			},
			true,
		);
		return $this;
	}

	/**
	 * JOIN met posts table voor post jobs.
	 *
	 * @deprecated Since v2.0.0. Use set_content_types() with jobs.content_type column instead.
	 * @return self
	 */
	public function join_with_posts(): self {
		$this->query->join( 'posts', 'id_from', '=', 'posts.ID' );
		return $this;
	}

	/**
	 * JOIN met terms table voor term jobs.
	 *
	 * @deprecated Since v2.0.0. Use set_content_types() with jobs.content_type column instead.
	 * @return self
	 */
	public function join_with_terms(): self {
		$this->query->join( 'terms', 'id_from', '=', 'terms.term_id' );
		$this->query->join( 'term_taxonomy', 'terms.term_id', '=', 'term_taxonomy.term_id' );
		return $this;
	}

	/**
	 * Filter op post types.
	 *
	 * @deprecated Since v2.0.0. Use set_content_types() instead (works without JOIN).
	 * @param array $post_types The post types.
	 * @return self
	 */
	public function set_post_types( array $post_types ): self {
		$this->query->whereIn( 'posts.post_type', $post_types );
		return $this;
	}

	/**
	 * Filter op taxonomies.
	 *
	 * @deprecated Since v2.0.0. Use set_content_types() instead (works without JOIN).
	 * @param array $taxonomies The taxonomies.
	 * @return self
	 */
	public function set_taxonomies( array $taxonomies ): self {
		$this->query->whereIn( 'term_taxonomy.taxonomy', $taxonomies );
		return $this;
	}

	/**
	 * Filter op post status.
	 */
	public function set_post_status( array $statuses ): self {
		$this->query->whereIn( 'posts.post_status', $statuses );
		return $this;
	}

	/**
	 * Include run data via JOIN.
	 */
	public function join_with_run_data(): self {
		global $wpdb;
		$this->query->join( $wpdb->prefix . 'pllat_bulk_runs AS r', 'run_id', '=', 'r.id' );
		return $this;
	}

	/**
	 * Set the id from.
	 */
	public function set_id_from( int $id_from ): self {
		$this->query->where( 'id_from', $id_from );
		return $this;
	}

	/**
	 * Filter op run statuses.
	 */
	public function set_run_statuses( array $statuses ): self {
		$this->query->whereIn( 'r.status', $statuses );
		return $this;
	}

	/**
	 * Add pagination.
	 */
	public function limit( int $limit, int $offset = 0 ): self {
		$this->query->limit( $limit )->offset( $offset );
		return $this;
	}

	/**
	 * Guarantee the output format.
	 *
	 * @param array $results The results.
	 * @return array
	 */
	private function guarantee_output_format( array $results ): array {
		if ( ARRAY_A === $this->output_format && 0 === \count( $results ) ) {
			return \array_map( static fn( $row ) => (array) $row, $results );
		}
		return $results;
	}

	/**
	 * Apply grouped OR inclusion filters if configured.
	 */
	private function apply_run_inclusion_filters(): void {
		if ( $this->or_filters_applied || 0 === \count( $this->or_group_filters ) ) {
			return;
		}

		if ( $this->requires_runs_join ) {
			$this->query->leftJoin(
				Run::TABLE_NAME,
				Job::TABLE_NAME . '.run_id',
				'=',
				Run::TABLE_NAME . '.id',
			);
		}

		$filters = $this->or_group_filters;
		$this->query->whereNested(
			static function ( Builder $q ) use ( $filters ): void {
				$is_first = true;
				foreach ( $filters as $filter ) {
					$filter( $q, $is_first );
					$is_first = false;
				}
			},
		);

		$this->or_filters_applied = true;
	}

	/**
	 * Add an OR-group filter and note required joins.
	 *
	 * @param callable(Builder,bool):void $filter The filter.
	 * @param bool                        $requires_runs_join Whether the runs join is required.
	 */
	private function add_or_filter( callable $filter, bool $requires_runs_join ): void {
		$this->or_group_filters[] = $filter;
		$this->requires_runs_join = $this->requires_runs_join || $requires_runs_join;
	}
}
