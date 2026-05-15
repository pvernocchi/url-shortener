# Contributing to URL Shortener

Thank you for considering a contribution! This guide explains how to collaborate on this project.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Reporting Issues](#reporting-issues)
- [Suggesting Features](#suggesting-features)
- [Submitting a Pull Request](#submitting-a-pull-request)
- [Coding Standards](#coding-standards)
- [Running the Tests](#running-the-tests)
- [License](#license)

---

## Code of Conduct

Be respectful and constructive in all interactions. Discriminatory, harassing, or offensive behaviour will not be tolerated.

---

## Getting Started

1. **Fork** the repository on GitHub.
2. **Clone** your fork locally:
   ```bash
   git clone https://github.com/<your-username>/url-shortener.git
   cd url-shortener
   ```
3. **Install** Composer dependencies (including dev dependencies):
   ```bash
   composer install
   ```
4. **Create a feature branch** from `main`:
   ```bash
   git checkout -b feature/my-improvement
   ```

---

## Reporting Issues

- Search [existing issues](https://github.com/pvernocchi/url-shortener/issues) before opening a new one.
- Provide a clear title, a description of the problem, steps to reproduce, and the expected vs. actual behaviour.
- Include your PHP version, web server, and any relevant error messages or log output.

---

## Suggesting Features

Open a [GitHub issue](https://github.com/pvernocchi/url-shortener/issues) with the label **enhancement**. Describe the use case, the proposed behaviour, and any alternatives you considered.

---

## Submitting a Pull Request

1. Ensure your branch is up to date with `main`:
   ```bash
   git fetch origin
   git rebase origin/main
   ```
2. Make your changes — keep commits focused and descriptive.
3. **Run the test suite** and make sure all tests pass (see [Running the Tests](#running-the-tests)).
4. Push your branch and open a pull request against `main`.
5. In the pull request description:
   - Summarise what the change does and why it is needed.
   - Reference any related issue (e.g. `Closes #42`).
6. A maintainer will review your PR and may request changes. Address feedback by pushing additional commits to the same branch.

---

## Coding Standards

- **PHP 8.1+** — use typed properties, named arguments, and match expressions where they improve clarity.
- Follow the existing namespace and directory structure (`src/Controllers`, `src/Core`, `src/Models`, `src/Services`, `src/Views`).
- Keep controllers thin — business logic belongs in services or models.
- Do not commit secrets, credentials, or generated files (`config/config.php`, `vendor/`, `storage/`).
- Write clear, self-documenting code; add comments only where the intent is not obvious.

---

## Running the Tests

The project uses **PHPUnit 10**. Run the full suite from the repository root:

```bash
./vendor/bin/phpunit --configuration tests/phpunit.xml
```

All tests must pass before a pull request can be merged. If you add new functionality, please add corresponding tests under the `tests/` directory.

---

## License

By contributing to this project you agree that your contributions will be licensed under the [GNU General Public License v3.0](LICENSE) that covers this project.
