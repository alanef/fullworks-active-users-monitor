# WordPress.org Assets Directory

This directory contains assets for the WordPress.org plugin repository.

## Required Files

- `banner-772x250.png` - Plugin banner (772x250 pixels)
- `banner-1544x500.png` - High-resolution plugin banner (1544x500 pixels) [Optional]
- `icon-128x128.png` - Plugin icon (128x128 pixels)
- `icon-256x256.png` - High-resolution plugin icon (256x256 pixels) [Optional]
- `screenshot-1.png`, `screenshot-2.png`, etc. - Screenshots referenced in readme.txt

## Notes

- These assets are automatically deployed to WordPress.org SVN when a release is created
- The deployment happens via GitHub Actions using the 10up/action-wordpress-plugin-deploy action
- Make sure to update screenshots when UI changes are made