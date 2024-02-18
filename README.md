# Silverstripe Elemental Layouts

A module allowing CMS users to define layouts, known as "skeletons", that provide a predefined set of elements when a page is created.

This module relies on native `$cascade_duplicates` settings to ensure sample content, files, images, etc. are copied to the page as a starter. The feature allowing pre-populated content and objects is configurable. [See the documentation for that feature here]().

## Requirements

- Silverstripe CMS ^5

## Installation

Ensure you setup a repository reference in your `composer.json` file:

```json
"repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:dynamic/silverstripe-elemental-skeletons.git"
        }
    ],
```


`composer require dnadesign/silverstripe-elemental-skeletons`

## License

See [License](LICENSE.md)

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
