# VCF Import WordPress Plugin

The VCF Import WordPress Plugin is a simple WordPress plugin that allows you to import contacts from a VCF file into your WordPress site. It provides an easy-to-use interface for selecting and uploading VCF files, and automatically creates new contacts based on the information in the file.

![Plugin Version](https://img.shields.io/badge/version-1.0.1-brightgreen.svg)
![License](https://img.shields.io/badge/license-GPLv3-blue.svg)
![PHP Version](https://img.shields.io/badge/requires%20PHP-7.4-orange.svg)

## Features

- Import user data from VCF (vCard) files.
- User-friendly interface within the WordPress dashboard.
- Create new users by importing their contact information.
- Supports categorization of imported contacts. You'll be able to import vcf's categories one by one
- Customize user roles for imported users.
- Easy-to-use and efficient user management tool.
- Imports photos embeded in vcf file, work with extension : [One User Avatar](https://wordpress.org/plugins/one-user-avatar/)

## Installation

Install first my other plugin : [nethttp.net-base-plugin](https://github.com/yrbane/nethttp.net-base-plugin)

To install the plugin, follow these steps:

1. Clone this repository or download the latest release from the [releases page](https://github.com/yrbane/nethttp.net-vcf-import/releases).
2. Upload the plugin files to the `/wp-content/plugins/` directory on your WordPress site.
3. Activate the "nethttp.net-vcf-import" plugin through the 'Plugins' screen in WordPress.

## Usage

To use the plugin, follow these steps:

1. Go to the 'VCF Import' screen in the WordPress admin area.
2. Click the 'Choose File' button and select the VCF file you want to import.
3. Click the 'Import' button to start the import process.
4. Wait for the import to complete. The plugin will create table where data are consigned by categorie and user. Check what you want to import and save at bottom of page. New contacts based on the information in the VCF file are created.
5. If you want to import images. You have to set a path where to store images.

## Contributing

Contributions to the VCF Import WordPress Plugin are welcome! If you find a bug or have a feature request, please open an issue on the [issue tracker](https://github.com/yrbane/nethttp.net-vcf-import/issues). If you want to contribute code, please fork the repository and submit a pull request.

## License

This project is licensed under the GPL-3.0 License - see the [LICENSE](LICENSE.md) file for details.

## Acknowledgments

- Built with [WordPress](https://wordpress.org/)
- Uses the [vCard class](https://github.com/jeroendesloovere/vcard) for VCF file parsing.
- Inspired by the need for efficient user management in WordPress.
