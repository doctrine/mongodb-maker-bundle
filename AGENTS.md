## Development Workflow

1. Install dependencies: `composer install`
2. Make code changes
3. Auto-fix style: `php vendor/bin/phpcbf`
4. Verify style: `php vendor/bin/phpcs`
5. Run static analysis: `php vendor/bin/phpstan`
6. Run tests: `php vendor/bin/phpunit`
7. Commit changes with clear, imperative message
8. Push and create pull request

## Key Requirements

- PHP 8.4+
- All code must pass `phpcs` (Doctrine Coding Standard)
- All tests must pass before committing
- Static analysis must pass
- No missing newlines at end of files
- No em dashes in text
