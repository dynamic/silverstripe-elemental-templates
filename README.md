# Silverstripe Elemental Layouts

A module for Silverstripe CMS that allows CMS users to define reusable page layouts, known as "templates" or "skeletons". These templates provide a predefined set of Elemental blocks that can be used to quickly create pages with consistent layouts and content.

[![CI](https://github.com/dynamic/silverstripe-elemental-templates/actions/workflows/ci.yml/badge.svg)](https://github.com/dynamic/silverstripe-elemental-templates/actions/workflows/ci.yml)  
[![Latest Stable Version](https://poser.pugx.org/dynamic/silverstripe-elemental-templates/v/stable)](https://packagist.org/packages/dynamic/silverstripe-elemental-templates)  
[![Total Downloads](https://poser.pugx.org/dynamic/silverstripe-elemental-templates/downloads)](https://packagist.org/packages/dynamic/silverstripe-elemental-templates)  
[![License](https://poser.pugx.org/dynamic/silverstripe-elemental-templates/license)](https://packagist.org/packages/dynamic/silverstripe-elemental-templates)  

## Features

- **Predefined Templates**: Create reusable templates with predefined Elemental blocks.
- **Page Creation from Templates**: Quickly create new pages based on existing templates.
- **Template Creation from Pages**: Generate templates from existing pages, including their Elemental blocks.
- **Configurable Defaults**: Populate Elemental blocks with default values defined in YAML configuration.

## Requirements

- Silverstripe CMS ^5
- DNADesign Elemental module ^5.2

## Installation

Install the module via Composer:

```bash
composer require dynamic/silverstripe-elemental-templates
```

Run `dev/build` to apply database changes.

## Usage

### Creating a Template

1. Navigate to the "Elemental Templates" section in the CMS.
2. Click "Add Template".
3. Fill in the template details:
   - **Title**: Name of the template.
   - **Page Type**: Select the page type this template is compatible with.
   - **Elements**: Add Elemental blocks to the template.
4. Save the template.

### Creating a Page from a Template

1. Go to the "Pages" section in the CMS.
2. Click "Add Page".
3. Select the desired page type.
4. In the "Template" dropdown, choose a template to apply to the new page.
5. Complete the remaining page details and save.

The selected template's Elemental blocks will be duplicated and added to the new page.

### Creating a Template from an Existing Page

1. Open the page you want to use as the basis for a template.
2. In the "More Options" menu, click "Create Blocks Template".
3. A new template will be created with the same Elemental blocks as the page.
4. Edit the template as needed in the "Elemental Templates" section.

## Populating Template Elements

The module supports pre-populating Elemental blocks with default values. This is configurable via YAML and supports database fields and relationships.

### Example YAML Configuration

```yaml
Dynamic\ElememtalTemplates\Models\Template:
  populate:
    DNADesign\Elemental\Models\ElementContent:
      Title: "Default Title"
      Content: "<p>Default content</p>"
```

**Note:** The `$populate` static configuration is checked on the `Template` class to determine whether default values should be applied when duplicating elements from a template.

## Roadmap

We recognize that the current implementation only supports configuring population definitions for database fields. Expanding this functionality to include other types of data is a priority, and we are actively working on adding this feature to enhance the module's flexibility and usability.

## Logging and Debugging

The module logs key actions, such as template creation and Elemental block duplication, to the Silverstripe log file (`silverstripe.log`). This can help diagnose issues during development.

## Contributing

Contributions are welcome! Please submit a pull request or open an issue on GitHub.

## License

This module is licensed under the BSD-3-Clause license. See the LICENSE file for details.

## Maintainers
 *  [Dynamic](http://www.dynamicagency.com) (<dev@dynamicagency.com>)

## Bugtracker
Bugs are tracked in the issues section of this repository. Before submitting an issue please read over
existing issues to ensure yours is unique.

If the issue does look like a new bug:

 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots
 and screencasts can help here.
 - Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version,
 Operating System, any installed SilverStripe modules.

Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.

## Development and contribution
If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
