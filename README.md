# Silverstripe Elemental Templates

A module for Silverstripe CMS that allows CMS users to define reusable page layouts, known as "templates" or "skeletons". These templates provide a predefined set of Elemental blocks that can be used to quickly create pages with consistent layouts and content.

[![CI](https://github.com/dynamic/silverstripe-elemental-templates/actions/workflows/ci.yml/badge.svg)](https://github.com/dynamic/silverstripe-elemental-templates/actions/workflows/ci.yml) [![Sponsors](https://img.shields.io/badge/GitHub-Sponsors-ff69b4?logo=github)](https://github.com/sponsors/dynamic)

[![Latest Stable Version](https://poser.pugx.org/dynamic/silverstripe-elemental-templates/v/stable)](https://packagist.org/packages/dynamic/silverstripe-elemental-templates)
[![Total Downloads](https://poser.pugx.org/dynamic/silverstripe-elemental-templates/downloads)](https://packagist.org/packages/dynamic/silverstripe-elemental-templates)
[![Latest Unstable Version](https://poser.pugx.org/dynamic/silverstripe-elemental-templates/v/unstable)](https://packagist.org/packages/dynamic/silverstripe-elemental-templates)
[![License](https://poser.pugx.org/dynamic/silverstripe-elemental-templates/license)](https://packagist.org/packages/dynamic/silverstripe-elemental-templates)

## Requirements

- PHP ^8.3
- dnadesign/silverstripe-elemental ^6.0
- lekoala/silverstripe-cms-actions ^2.0
- silverstripe/linkfield ^5.0
- silverstripe/vendor-plugin ^3

## Installation

```bash
composer require dynamic/silverstripe-elemental-templates
```

Run `dev/build` to apply database changes.

## Features

- **Predefined Templates**: Create reusable templates with predefined Elemental blocks.
- **Categories**: Group templates (Heroes, Cards & Grids, Content, ...) so both pickers stay
  organised as the library grows. The option list is configurable.
- **Two ways to apply a template**:
  - a grouped **"Step 3" dropdown** in the Add-new-page flow, and
  - a visual, category-grouped **"Apply Template to Page"** picker on a page's Content tab
    (with preview thumbnails).
- **Template Creation from Pages**: Generate templates from existing pages, including their Elemental blocks.
- **Universal templates**: A template with no Page Type applies to any elemental page type.
- **Configurable Defaults**: Populate Elemental blocks with default values defined in YAML configuration.

## Template Categories

Templates carry a `Category`, shown as a column in the Element Templates admin and used to group
both pickers. The available options are configured on the `Template` model and can be extended per
project:

```yaml
Dynamic\ElementalTemplates\Models\Template:
  template_categories:
    - 'Heroes'
    - 'Cards & Grids'
    - 'Content'
    # ...your categories
```

This list is numerically indexed, so project YAML **appends** to the defaults. To replace the
defaults, reset the config first (`template_categories: null`) and then declare the full list.
Uncategorised templates are grouped last; if no template has a category, the pickers render as a
flat list with no headings.

## Usage

### Creating a Template

1. Navigate to the "Elemental Templates" section in the CMS.
2. Click "Add Template".
3. Fill in the template details:
   - **Title**: Name of the template.
   - **Category**: Optional grouping used by the pickers.
   - **Page Type**: The page type this template is compatible with. Leave empty for a universal
     template that applies to any elemental page type.
   - **Elements**: Add Elemental blocks to the template.
4. Save the template. Use **Capture Preview Image** to generate a thumbnail for the pickers.

### Creating a Page from a Template

Two entry points:

**From the Add-page flow** — Pages → Add Page → choose a page type, then pick a template from the
grouped **"Step 3"** dropdown. The template's blocks are applied to the new page on create.

**From an existing page** — open the page, expand **"Apply Template to Page"** on the Content tab,
choose a template from the category-grouped visual picker (with preview thumbnails), and click
**Apply Template to Page**. The template's blocks are appended in place.

In both cases the template's Elemental blocks are duplicated onto the page.

### Creating a Template from an Existing Page

1. Open the page you want to use as the basis for a template.
2. In the "More Options" menu, click "Create Blocks Template".
3. A new template will be created with the same Elemental blocks as the page.
4. Edit the template as needed in the "Elemental Templates" section.

## Populating Template Elements

The module supports pre-populating Elemental blocks with default values. This is configurable via YAML and supports database fields and relationships.

### Example YAML Configuration

```yaml
Dynamic\ElementalTemplates\Models\Template:
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

## Getting more elements

See [Elemental modules by Dynamic](https://github.com/orgs/dynamic/repositories?q=elemental&type=all&language=&sort=)

## Maintainers

 *  [Dynamic](https://www.dynamicagency.com) (<dev@dynamicagency.com>)

## Bugtracker

Bugs are tracked in the issues section of this repository. Before submitting an issue please read over existing issues to ensure yours is unique.

If the issue does look like a new bug:

 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots and screencasts can help here.
 - Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version, Operating System, any installed SilverStripe modules.

Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.

## Development and contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.

## License

See [License](LICENSE.md)
