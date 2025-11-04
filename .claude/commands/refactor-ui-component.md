/refactor-ui-component

Analyze and refactor this React component following modern best practices:

1. **Context & Usage Analysis**

   - Review how parent components use this component
   - Identify all prop usages and their patterns
   - Check for any implicit dependencies or expectations
   - Note any breaking changes that would affect parent components

2. **Code Analysis**

   - Identify code smells and anti-patterns
   - Evaluate helper methods - can they be simplified or extracted?
   - Check for unnecessary complexity or redundant logic

3. **Props & API Design**

   - Improve prop names for clarity and consistency
   - Consider prop value types - can we use more semantic types?
   - Identify opportunities for prop composition or defaults
   - Suggest TypeScript improvements if applicable
   - **Maintain backward compatibility with parent components** unless explicitly justified

4. **Component Structure**

   - Extract logical sub-components where it improves readability
   - Apply proper component composition patterns
   - Consider creating a component folder structure if multiple files are needed
   - Recommend custom hooks for complex logic

5. **Modern React Patterns**

   - Apply performance optimizations (React.memo, useMemo, useCallback) where beneficial
   - Use modern hooks patterns and best practices
   - Ensure proper dependency arrays

6. **Code Quality**

   - Improve readability and maintainability
   - Add accessibility attributes where missing
   - Consistent naming conventions
   - Remove dead code

7. **Output**
   - Explain the reasoning behind major changes
   - **Document any breaking changes and their impact on parent components**
   - Suggest migration steps if API changes are necessary
   - If creating multiple files, specify the folder structure
   - Note the trade-offs of proposed changes

Maintain existing functionality and component API unless improvements are clearly beneficial. Prioritize readability and maintainability while **avoiding breaking changes to parent components**.
