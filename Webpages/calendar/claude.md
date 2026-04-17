# Claude Refactoring Prompt

## Role
Act as a senior software engineer specializing in PHP (WordPress) and modern JavaScript.

Your task is to refactor the provided codebase to meet strict engineering standards while preserving all existing functionality.

---

## GOALS
- Improve readability, maintainability, and structure  
- Enforce consistent coding standards  
- Reduce complexity and duplication  
- Keep PHP and JavaScript completely separate (do NOT merge them)  

---

## CODE STYLE & READABILITY
- Use clear, descriptive naming conventions:
  - PHP: `snake_case`
  - JavaScript: `camelCase`
- Break large functions into smaller modular functions (max ~20–30 lines each)
- Follow SOLID principles where applicable
- Avoid deeply nested conditionals where possible
- Add meaningful comments explaining **WHY** logic exists (not just WHAT it does)
- Use JSDoc (JS) and PHPDoc (PHP) for all functions

---

## IMPLEMENTATION STANDARDS
- Add proper error handling:
  - PHP: guard clauses, null checks
  - JS: try/catch where needed
- Sanitize and escape all outputs properly (WordPress standards: `esc_html`, `esc_attr`, etc.)
- Do NOT introduce any security risks (no unsafe DOM injection, no unsanitized data usage)
- Optimize performance:
  - Avoid redundant loops
  - Cache repeated calculations where possible
  - Reduce DOM queries in JS

---

## STRUCTURE REQUIREMENTS

### PHP
- Separate data processing from rendering logic
- Create helper functions for:
  - event formatting
  - date range generation
  - calendar cell rendering

### JavaScript
- Separate concerns into clear modules/functions:
  - initialization
  - event handling
  - DOM updates
- Avoid duplicated DOM queries (cache selectors)
- Use a single initialization entry point

---

## OUTPUT FORMAT

Return your response in **THREE sections**:

### 1. Refactoring Summary
- Brief explanation of major improvements made

### 2. Refactored PHP
- Full PHP code in one block
- Must be clean, modular, and documented

### 3. Refactored JavaScript
- Full JS code in one block
- Must be modular, readable, and documented

---

## IMPORTANT
- Keep PHP and JavaScript completely separate  
- Do NOT remove any functionality  
- Do NOT simplify business logic incorrectly  
- Do NOT introduce frameworks or dependencies  
- Maintain compatibility with WordPress + ACF  

---

## CODE TO REFACTOR

See calendar.js and calendar.php files in the calendar folder