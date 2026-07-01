# Upgrade Guide to Silverstripe CMS 6

This document outlines the necessary changes to upgrade your project to be compatible with `sunnysideup/cron-jobs` for Silverstripe CMS 6.

## Core Dependency Updates

⚠️ **BREAKING CHANGE**: The following core dependencies have been updated. You must update your project's `composer.json` to match these versions.

- **`silverstripe/framework`**: `^5.0` → `^6.0`
- **`silverstripe/admin`**: `^2.0` → `^3.0`
- **`sunnysideup/cms-niceties`**: `5.x-dev` → `^7.0`

## API Changes & New Requirements

### `BuildTask` Implementation

⚠️ **BREAKING CHANGE**: `BuildTask` subclasses have been updated to align with the new Silverstripe 6 command-line execution model using the `symfony/console` component. Web-based execution of build tasks is deprecated.

- `run(\$request)` has been replaced with `execute(InputInterface \$input, PolyOutput \$output)`.
- HTTP request parameters (e.g., `\$request->getVar('recipe')`) are now handled via command-line options (e.g., `\$input->getOption('recipe')`).
- Output is now written using the `PolyOutput` object (e.g., `\$output->writeln('...')`) instead of `DB::alteration_message` or `echo`.

**Affected Classes:**
- `SiteUpdateLogsDelete`
- `SiteUpdateReset`
- `SiteUpdateRun`

### PHP 8 `#[Override]` Attribute

All methods that override a method from a parent class now use the `#[Override]` attribute. This is a new requirement in PHP 8 to make inheritance explicit.

**Affected Classes:**
- `SiteUpdatesAdmin`
- `CustomGridFieldDataColumns`
- `SiteUpdateDropdownField`
- `SiteUpdateStepDropdownField`
- `SiteUpdateRunNext`
- `SiteUpdate`
- `SiteUpdateStep`
- `SiteUpdateConfig`
- `CleanUpSiteUpdatesRecipe`
- `CustomRecipe`
- `TestRecipe`
- `SiteUpdateRun`

### Namespace and Class Updates

⚠️ **BREAKING CHANGE**: Several classes have been moved to new namespaces in Silverstripe 6. You must update all `use` statements in your project accordingly.

- `SilverStripe\ORM\ArrayList` and `SilverStripe\View\ArrayData` are now located in `SilverStripe\Model\List\ArrayList` and `SilverStripe\Model\ArrayData` respectively.
- `SilverStripe\View\ViewableData` has been replaced with `SilverStripe\Model\ModelData`.

**Affected Classes:**
- `SiteUpdatesAdmin`
- `AnalysisBaseClass`
- `SiteUpdateController`
- `SiteUpdateRecipeBaseClass`
- `BaseMethodsForRecipesAndSteps`
- `Graph`

### Type Hinting and Method Signatures

Method signatures have been updated to include stricter type hinting as required by the upgraded dependencies.

- `SiteUpdateRunNext::runOneStep`: The check for the existence of `$runNextObject` is now more strict (`instanceof DataObject`).
- `SiteUpdateLogsDelete::truncateTable`: Now requires a `PolyOutput` argument.
- `SiteUpdateRun::doTheActualRun`: The `\$request` parameter has been replaced with `InputInterface \$input` and `PolyOutput \$output`.
- `BuildTask` properties (`$title`, `$description`, `$commandName`) are now strongly typed (`string`, `protected static string`).

🚨 **CRITICAL REVIEW REQUIRED / RISKY**: The change in `SiteUpdateRunNext::runOneStep` from a simple truthy check (`if ($runNextObject)`) to `if ($runNextObject instanceof DataObject)` could potentially introduce subtle bugs if the original code relied on a different type of object being returned. **Review your implementation to ensure this change does not affect custom logic.**
