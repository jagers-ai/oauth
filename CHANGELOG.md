# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0-alpha] - 2025-01-03

### Added
- Initial alpha release
- Core OAuth 2.0 implementation with PKCE (S256) support
- Google OAuth provider with ID token validation
- Session-based storage implementation
- CSRF protection with 128-bit state tokens
- League OAuth2 Client wrapper for extensibility
- PSR-18/17 HTTP client support
- Happy Path unit tests
- Basic usage examples
- Documentation (README, Installation, Integration guides)
- GitHub Actions CI workflow

### Security
- PKCE enabled by default for all OAuth flows
- ID token validation using Google's JWK keys
- Secure state token generation and validation
- One-time use state tokens

### Known Limitations
- Session storage only (database storage coming soon)
- Google provider only (Kakao/Naver coming soon)
- No refresh token support yet
- Alpha release - API may change