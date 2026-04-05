Based on the provided engineering standards (`php.md`), the current state of the application represents a classic "legacy monolith" pattern: presentation (HTML/CSS/JS), infrastructure (PDO/SQL), orchestration (AJAX/form handling), and domain logic are tightly coupled in root-level monolithic files (`index.php` and `recurring.php`). Furthermore, it violates key security, directory structure, and modern PHP type safety standards.

Here is a comprehensive, step-by-step architectural plan to bring the application into compliance with the strict engineering standards.

---

### Phase 1: Directory Restructuring and Entry Points
**Goal:** Comply with public directory isolation (1.2.2) and remove entry points from the project root.

1. **Create a `public/` directory:**
   * Move `index.php` and `recurring.php` to `public/`.
   * Configure the local web server to use `public/` as the document root. This prevents direct access to `src/`, `config/`, and `vendor/`.
2. **Consolidate configuration and bootstrapping:**
   * Create a `bootstrap.php` (or similar) outside the `public/` folder to handle reading `config.ini`, establishing the PDO connection, and initializing the autoloader.
   * Remove the repetitive `parse_ini_file` and PDO initialization blocks currently duplicated in `index.php`, `recurring.php`, and `bin/task.php`.
3. **Fix Shebang usage:**
   * Remove the `#!/usr/bin/env php` shebang from `src/TaskProcessor.php`. It is a library class, not an executable script. Ensure the shebang is *only* present in `bin/task.php`.

### Phase 2: Separation of Concerns & MVC Implementation
**Goal:** Separate domain logic, infrastructure, and presentation (1.1.1), and eliminate global state/superglobal dependency (1.1.2).

1. **Introduce a routing mechanism:**
   * Consolidate `public/index.php` and `public/recurring.php` into a single Front Controller (`public/index.php`) that routes requests based on the URI.
2. **Extract Controllers:**
   * Extract the huge `if (isset($_GET['ajax']))` blocks and POST handlers into dedicated Controller classes (e.g., `TaskController`, `RecurringTaskController`, `GroupController`).
   * Controllers **MUST** read superglobals (`$_POST`, `$_GET`), validate the input (4.2.1), and pass strictly typed scalar values or Data Transfer Objects (DTOs) to the domain layer.
3. **Extract Views (Templates):**
   * Install Twig via Composer (`composer require twig/twig`).
   * Move all HTML, inline CSS, and JavaScript out of PHP files into a dedicated `templates/` directory using the `.twig` extension (e.g., `templates/tasks/index.twig`, `templates/tasks/recurring.twig`).
   * Initialize a `Twig\Environment` in `bootstrap.php` and inject it into Controllers.
   * Ensure templates only handle display logic (loops, Twig auto-escaping) and are completely stripped of database queries and business logic.

### Phase 3: Infrastructure and Dependency Injection
**Goal:** Decouple infrastructure via DI (1.1.3) and establish layer boundaries (1.3.1).

1. **Implement Dependency Injection:**
   * Refactor Controllers, Processors, and Services to accept their dependencies (like `PDO` or Repositories) via the constructor.
2. **Refactor the Persistence Layer (Repositories):**
   * Rename `TaskDatabase.php` and `TaskStorage.php` to reflect standard Repository patterns (e.g., `RecurringTaskRepository`, `TaskRepository`, `TaskGroupRepository`).
   * Move procedural database queries (like `getDueCount` and the queries floating inside `index.php`) into these Repositories.
   * Define Interfaces for these repositories (e.g., `TaskRepositoryInterface`) and type-hint the interfaces in the application layer.

### Phase 4: Modern PHP Features and Type Safety
**Goal:** Ensure 100% strict typing, PHP 8.3 features, and robust error handling (2.2, 3.1, 4.1).

1. **Apply `strict_types` globally:**
   * Add `declare(strict_types=1);` to the top of **all** PHP files, notably `index.php`, `recurring.php`, and `bin/task.php` where it is currently missing.
2. **Upgrade to Readonly Classes (2.2.2):**
   * Mark Data Transfer Objects and immutable domain models (like `Task.php`) as `readonly` classes.
3. **Enforce Type Declarations (2.2.1):**
   * Ensure all class properties, method parameters, and return types have explicit declarations. Remove any implicit `mixed` assumptions.
4. **Fix Error Handling (4.1.2):**
   * Replace all instances of `die("Error message\n");` (seen in setup/bootstrap logic) with thrown `RuntimeException`s or domain-specific exceptions. Let a global exception handler output the correct HTTP or CLI error.

### Phase 5: Security and Resiliency
**Goal:** Enforce critical security standards regarding XSS, CSRF, and injection vectors (5).

1. **Add CSRF Protection (5.4.2):**
   * Currently, state-changing POST and AJAX requests lack CSRF verification. Implement session-based CSRF tokens and require them on all `POST`/`DELETE`/`UPDATE` operations.
2. **Normalize API Responses (4.2.2):**
   * Standardize the AJAX JSON responses. Instead of ad-hoc `['success' => true/false]`, use a structured format (like RFC 7807 Problem Details) for error states.
3. **Strict Parameter Allowlisting (5.2.2):**
   * Verify all inputs. For instance, when validating `$_POST['frequency_type']`, validate it against cases in the `Frequency` Enum rather than raw strings.
4. **HTML Output Escaping (5.4.1):**
   * The custom `formatDetails` function attempts to parse and replace URLs. Ensure this logic is rock-solid against XSS, or utilize an established library for markdown/link parsing.

### Phase 6: Code Quality Gates (CI/CD alignment)
**Goal:** Automate formatting and static analysis (2.1.2, 7.3).

1. **Automated Formatting:**
   * Run the configured `php-cs-fixer` (already in `composer.json`) across the entire codebase to bring spacing, brace placement, and imports into PSR-12 compliance.
2. **Static Analysis:**
   * Run `phpstan analyse` (currently mapped in the `composer.json` script) at level 8 to catch any edge cases, missing PHPDoc generics on arrays (`@return list<Task>`), and mixed type usage.

---

### Suggested Execution Order

To minimize disruption, execute the plan in this order:
1. Add `declare(strict_types=1)` and fix immediate type errors / run `php-cs-fixer`.
2. Move `index.php` and `recurring.php` to `public/` and extract the config/PDO bootstrapping.
3. Install Twig, separate the HTML chunks from the PHP logic into a `templates/` folder, and convert them to `.twig` syntax.
4. Extract the procedural code and AJAX handling into properly injected Controller and Repository classes.
5. Add CSRF protection and formalize the Error Handling.
