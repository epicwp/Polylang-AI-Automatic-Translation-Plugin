<?php
namespace PLLAT\Translator\Enums;

enum TaskStatus: string {
    case Pending   = 'pending';
    case Completed = 'completed';
    case Failed    = 'failed';

    public function isCompleted(): bool {
        return self::Completed === $this;
    }

    public function isFailed(): bool {
        return self::Failed === $this;
    }

    public function isPending(): bool {
        return self::Pending === $this;
    }
}
