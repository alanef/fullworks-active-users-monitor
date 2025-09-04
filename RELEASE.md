# Release Process

This document outlines the release process for the Fullworks Active Users Monitor plugin.

## Prerequisites

### GitHub Repository Secrets

The following secrets must be configured in your GitHub repository settings:

1. **SVN_USERNAME** - Your WordPress.org username
2. **SVN_PASSWORD** - Your WordPress.org password

To add these secrets:
1. Go to Settings > Secrets and variables > Actions in your GitHub repository
2. Click "New repository secret"
3. Add each secret with the appropriate value

## Release Workflow

The release process is automated via GitHub Actions and triggers when you create a new version tag.

### Steps to Create a Release

1. **Update Version Numbers**
   ```bash
   # Update version in main plugin file
   # Version: X.X.X
   # Update FWAUM_VERSION constant
   ```

2. **Update Changelog**
   - Update `readme.txt` with the new version changelog
   - Update `Stable tag:` to the new version number

3. **Commit Changes**
   ```bash
   git add .
   git commit -m "chore: Prepare release vX.X.X"
   git push origin main
   ```

4. **Create and Push Tag**
   ```bash
   # Create a tag (version must match the plugin version)
   git tag vX.X.X
   git push origin vX.X.X
   ```

5. **Automated Process**
   Once you push the tag, GitHub Actions will:
   - Run quality checks (PHPCS, PHPCompatibility, Plugin Check)
   - Build the plugin
   - Create a GitHub release with the plugin ZIP
   - Deploy to WordPress.org SVN repository

## Version Tag Formats Supported

- `vX.X.X` (e.g., v1.7.3)
- `X.X.X` (e.g., 1.7.3)
- `vX.X` (e.g., v2.3)
- `X.X` (e.g., 2.3)

## Workflow Stages

1. **Quality Checks** (`checks.yml`)
   - Validates composer.json
   - Runs PHPCS WordPress Coding Standards
   - Checks PHP compatibility
   - Verifies version consistency
   - Runs WordPress Plugin Check

2. **Release** (`release.yml`)
   - Verifies tag matches plugin version
   - Builds the plugin with production dependencies
   - Creates GitHub release with ZIP files
   - Deploys to WordPress.org SVN

## WordPress.org Assets

Place the following files in the `.wordpress-org` directory:
- `banner-772x250.png` - Plugin banner
- `banner-1544x500.png` - High-resolution banner (optional)
- `icon-128x128.png` - Plugin icon
- `icon-256x256.png` - High-resolution icon (optional)
- `screenshot-*.png` - Screenshots referenced in readme.txt

These assets are automatically deployed to WordPress.org during the release process.

## Troubleshooting

### Version Mismatch Error
- Ensure the tag version matches the plugin version in the main file
- Check both `Version:` header and `FWAUM_VERSION` constant

### SVN Deployment Failed
- Verify SVN_USERNAME and SVN_PASSWORD secrets are set correctly
- Check that your WordPress.org account has commit access to the plugin

### Build Failed
- Run `composer validate` locally
- Run `npm run lint:php` to check for coding standards issues
- Ensure all dependencies are properly declared

## Manual SVN Deployment (Emergency Only)

If automated deployment fails, you can manually deploy:

```bash
# Checkout SVN repository
svn co https://plugins.svn.wordpress.org/fullworks-active-users-monitor svn-fullworks-active-users-monitor

# Copy files to trunk
cp -r fullworks-active-users-monitor/* svn-fullworks-active-users-monitor/trunk/

# Copy assets
cp -r .wordpress-org/* svn-fullworks-active-users-monitor/assets/

# Tag the release
svn cp trunk tags/X.X.X

# Commit
svn ci -m "Release version X.X.X"
```

## Release Checklist

- [ ] Version updated in main plugin file
- [ ] FWAUM_VERSION constant updated
- [ ] Changelog added to readme.txt
- [ ] Stable tag updated in readme.txt
- [ ] All tests passing locally
- [ ] PHPCS checks passing (`npm run lint:php`)
- [ ] Changes committed and pushed
- [ ] Tag created and pushed
- [ ] GitHub Actions workflow successful
- [ ] Plugin available on WordPress.org