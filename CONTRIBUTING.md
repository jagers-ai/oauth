# Contributing to HolyOAuth

Thank you for your interest in contributing to HolyOAuth! We welcome contributions from the community.

## How to Contribute

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/your-username/holy-oauth.git
   cd holy-oauth
   ```
3. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```
4. **Make your changes** and commit them:
   ```bash
   git commit -m "Add feature: description"
   ```
5. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```
6. **Open a Pull Request** on GitHub

## Development Setup

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run code style checks
composer phpcs

# Run static analysis
composer phpstan
```

## Coding Standards

- Follow PSR-12 coding standards
- Write tests for new features
- Keep code coverage above 60%
- Document public methods with PHPDoc

## Testing

- Write unit tests for all new features
- Ensure all tests pass before submitting PR
- Add integration tests for new providers

## Pull Request Guidelines

- Describe what your PR does
- Reference any related issues
- Ensure CI passes
- Update documentation if needed
- Add entry to CHANGELOG.md

## Adding New OAuth Providers

1. Create provider class in `src/Providers/`
2. Extend `LeagueProviderWrapper` or implement `OAuthInterface`
3. Add tests in `tests/Providers/`
4. Update documentation
5. Add example usage

## Questions?

Feel free to open an issue for any questions!