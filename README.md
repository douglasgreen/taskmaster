# TaskMaster

TaskMaster is a flexible, dual-interface (Web & CLI) to-do list manager built with PHP 8.3. It allows users to manage one-time tasks organized by groups, as well as set up highly customizable recurring tasks that automatically generate actionable to-dos.

## ✨ Features

- **Task Groups:** Organize one-time tasks into separate workspaces or categories.
- **Advanced Recurring Tasks:** Schedule tasks daily, weekly, monthly, or yearly with exact parameters (e.g., specific days of the month, days of the week, or precise times of day).
- **Responsive Web UI:** A modern, mobile-friendly interface built with Twig and Bootstrap 5, featuring AJAX-powered interactions (no page reloads for common actions).
- **CLI Utilities:** A command-line interface for searching, adding, and processing background tasks.
- **Keyboard Shortcuts:** Power-user friendly hotkeys for rapid navigation and task creation.
- **Smart Date Parsing:** Display relative due dates (e.g., "yesterday", "tomorrow", "in 5 days").

## 📋 Requirements

- **PHP:** 8.3 or higher.
- **Database:** MySQL or MariaDB.
- **Composer:** For managing PHP dependencies.
- **Web Server:** Nginx, Apache, or the PHP built-in server (for local development).

## 🚀 Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone git@github.com:douglasgreen/taskmaster.git
   cd taskmaster
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Database Setup:**
   Create the database and required tables using the provided schema file:
   ```bash
   mysql -u your_user -p < docs/schema.sql
   ```
   *Note: The schema creates a database named `nurdsite_task_manager`.*

4. **Configuration:**
   Copy the sample configuration file and update it with your database credentials:
   ```bash
   cp config/config.ini.sample config/config.ini
   ```
   *Make sure the `db` parameter in `config.ini` matches the database name created by the schema (e.g., `nurdsite_task_manager`).*

5. **Web Server Setup:**
   Point your web server's document root to the `public/` directory. Alternatively, you can use PHP's built-in server for quick local testing:
   ```bash
   php -S localhost:8000 -t public/
   ```
   Visit `http://localhost:8000` in your browser.

## ⚙️ Automating Recurring Tasks (Cron Job)

To ensure your recurring tasks are automatically evaluated and added to your actionable task list, you must run the background processor regularly.

Add the following entry to your crontab to process tasks every minute (or as preferred):

```cron
* * * * * /usr/bin/php /path/to/taskmaster/bin/task.php process >> /dev/null 2>&1
```

## 💻 Usage

### Web Interface
- **Tasks:** Add one-time tasks, assign due dates, add URLs/details, and organize them by group.
- **Recurring:** Switch to the "Recurring" view via the sidebar to configure tasks that repeat based on specific schedules.

**Keyboard Shortcuts:**
- <kbd>N</kbd> - Add a new task
- <kbd>/</kbd> - Focus the search bar
- <kbd>S</kbd> - Toggle the mobile sidebar
- <kbd>?</kbd> - Show the shortcut help menu
- <kbd>ESC</kbd> - Close active modals/dialogs

### CLI Interface
TaskMaster includes a command-line script located at `bin/task.php`.

**Process pending recurring tasks:**
```bash
./bin/task.php process
```

**Search for recurring tasks:**
```bash
./bin/task.php search --term="Review"
```

**Add a recurring task via CLI:**
```bash
./bin/task.php add --name="Weekly Review" --week="5" --time="15:00"
```

## 🛠️ Development & Quality Assurance

This project includes a robust set of QA tools integrated via Composer scripts. These are ideal for use in GitLab CI/CD pipelines.

- **Run all tests (PHPUnit):**
  ```bash
  composer test
  ```
- **Run Unit/Integration tests specifically:**
  ```bash
  composer test:unit
  composer test:integration
  ```
- **Run all linting and static analysis (Dry-run):**
  ```bash
  composer qa
  ```
  *(This runs `php-linter`, `php-cs-fixer`, `phpstan`, and `rector`)*
- **Automatically fix code style and modernizations:**
  ```bash
  composer lint:fix
  ```

### GitLab CI/CD Example
To automate checks, you can create a `.gitlab-ci.yml` utilizing the predefined composer scripts:
```yaml
image: php:8.3-cli

cache:
  paths:
    - vendor/

before_script:
  - apt-get update && apt-get install -y git unzip
  - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
  - composer install --prefer-dist --no-ansi --no-interaction --no-progress

test:
  script:
    - composer test

quality-assurance:
  script:
    - composer qa
```

## 📄 License

This project is licensed under the [MIT License](LICENSE).
Copyright (c) 2024-2026 Douglas S. Green.
