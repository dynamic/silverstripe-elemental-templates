<%-- Fallback for the "Requirements" include used by Dynamic\ElementalTemplates\Layout\TemplatePreviewContent.
     Sites running silverstripe-essentials-theme get that theme's real Requirements include instead:
     themes are resolved before $default (module) templates, so this empty version is only
     used where no theme provides one, which keeps the content-only preview rendering (200)
     rather than failing with MissingTemplateException. --%>
