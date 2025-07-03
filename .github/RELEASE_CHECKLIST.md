# Release Checklist for v0.1.0-alpha

## Pre-release Steps

- [x] All code implemented
  - [x] Core interfaces
  - [x] League OAuth2 wrapper with PKCE
  - [x] Google Provider
  - [x] State Manager
  - [x] Token Validator
  - [x] Session Storage
  - [x] OAuth Manager

- [x] Documentation complete
  - [x] README.md with quick start
  - [x] Installation guide
  - [x] Integration guide
  - [x] License (MIT)
  - [x] Contributing guide
  - [x] Changelog

- [x] Tests written
  - [x] Happy path tests
  - [x] PHPUnit configuration

- [x] CI/CD configured
  - [x] GitHub Actions workflow
  - [x] Multiple PHP version testing

- [ ] Final checks
  - [ ] Run `composer validate --strict`
  - [ ] Run tests locally
  - [ ] Check all links in documentation
  - [ ] Verify examples work

## Release Steps

1. **Create GitHub repository**
   ```bash
   git init
   git add .
   git commit -m "Initial commit: v0.1.0-alpha"
   git remote add origin https://github.com/holyhabit/oauth.git
   git push -u origin main
   ```

2. **Create release tag**
   ```bash
   git tag -a v0.1.0-alpha -m "Initial alpha release"
   git push origin v0.1.0-alpha
   ```

3. **Create GitHub Release**
   - Go to https://github.com/holyhabit/oauth/releases/new
   - Choose tag: v0.1.0-alpha
   - Release title: v0.1.0-alpha - Initial Alpha Release
   - Mark as pre-release
   - Copy content from CHANGELOG.md

4. **Submit to Packagist**
   - Go to https://packagist.org/packages/submit
   - Enter repository URL: https://github.com/holyhabit/oauth
   - Set up auto-update webhook

## Post-release

- [ ] Verify package on Packagist
- [ ] Test installation: `composer require holyhabit/oauth:^0.1.0-alpha`
- [ ] Update Holy Habit project to use the package
- [ ] Announce release

## Next Release Planning

- [ ] Gather feedback from alpha users
- [ ] Plan features for v0.2.0:
  - [ ] Database storage adapter
  - [ ] Refresh token support
  - [ ] Kakao provider
  - [ ] Naver provider