# Fullworks Active Users Monitor

Real-time monitoring of logged-in WordPress users with visual indicators, filtering, and comprehensive admin tools.

## About This Plugin

**Fullworks Active Users Monitor** provides administrators with real-time visibility of logged-in users on WordPress sites. Using WordPress's native session tokens system, this plugin accurately tracks user login states and provides powerful monitoring tools.

For detailed plugin information, features, and usage instructions, please see the [plugin readme](fullworks-active-users-monitor/readme.txt).

## Project Structure

```
fullworks-active-users-monitor/
├── .github/                      # GitHub Actions workflows
│   ├── workflows/
│   │   └── release.yml           # Automated release builds
│   └── ISSUE_TEMPLATE/           # Issue templates
├── fullworks-active-users-monitor/  # Main plugin directory
│   ├── fullworks-active-users-monitor.php  # Main plugin file
│   ├── readme.txt                # WordPress.org readme
│   ├── includes/                 # Core plugin classes
│   │   ├── class-user-tracker.php
│   │   ├── class-admin-bar.php
│   │   ├── class-settings.php
│   │   └── ...
│   ├── admin/                    # Admin-specific functionality
│   ├── languages/                # Translation files
│   ├── uninstall.php            # Cleanup on uninstall
│   └── .distignore              # Build exclusions
├── tests/                        # PHPUnit tests
├── bin/                          # Build and setup scripts
├── .wp-env.json                  # Local development config
├── composer.json                 # PHP dependencies
├── package.json                  # Node dependencies
└── phpcs.xml.dist               # Coding standards config
```

## Development Setup

### Prerequisites

- **PHP**: 7.4 or higher
- **WordPress**: 5.9 or higher
- **Node.js**: 18.0.0 or higher
- **npm**: 8.0.0 or higher
- **Composer**: 2.0 or higher

### Quick Start

```bash
# Clone the repository
git clone https://github.com/alanef/fullworks-active-users-monitor.git
cd fullworks-active-users-monitor

# Install dependencies
composer install
npm install

# Start development environment
npm run env:start
```

Access the local WordPress site at http://localhost:8888 (admin/password).

### Available Commands

```bash
# Development Environment
npm run env:start       # Start local WordPress
npm run env:stop        # Stop environment
npm run env:reset       # Reset environment
npm run env:cli         # Access WP-CLI

# Code Quality
npm run lint:php        # Check PHP coding standards
npm run lint:php:fix    # Fix PHP coding standards
npm run test            # Run PHPUnit tests

# Build & Release
npm run build           # Build release package
```

## Contributing

Contributions are welcome! We have several open issues tagged as "help wanted" that are great starting points for contributors.

### How to Contribute

1. Check our [open issues](https://github.com/alanef/fullworks-active-users-monitor/issues) for "help wanted" tags
2. Fork the repository
3. Create a feature branch (`git checkout -b feature/your-feature`)
4. Make your changes following WordPress coding standards
5. Run tests and linting: `npm run lint:php` and `npm run test`
6. Commit your changes with clear messages
7. Push to your branch
8. Create a Pull Request with a detailed description

### Development Guidelines

- Follow WordPress Coding Standards (enforced via PHPCS)
- Add appropriate PHPDoc comments
- Include unit tests for new features
- Update documentation as needed
- Ensure all existing tests pass
- Test in the local development environment

### Key Files for Contributors

- [CLAUDE.md](CLAUDE.md) - AI assistant instructions and project guidelines
- [AI-WORDPRESS-PLUGIN-PROMPT.md](AI-WORDPRESS-PLUGIN-PROMPT.md) - WordPress.org compliance requirements
- [Plugin Readme](fullworks-active-users-monitor/readme.txt) - User-facing documentation

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [wp-env Documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)

## License

This plugin is licensed under GPL v2 or later. See [LICENSE](LICENSE) for details.

## Support

- **Issues**: [GitHub Issues](https://github.com/alanef/fullworks-active-users-monitor/issues)
- **Donations**: [Ko-fi](https://ko-fi.com/wpalan)
- **Author**: [Fullworks](https://fullworks.net/)