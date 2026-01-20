<!DOCTYPE html>
<html lang="$ContentLocale">
<head>
    <% base_tag %>
    <!-- Force desktop viewport for proper responsive breakpoints in screenshots -->
    <meta name="viewport" content="width=1400, initial-scale=1">
    $MetaTags(false)

    <title>$Template.Title - Preview</title>

    <% include Favicons %>
    <% include Requirements %>
    <% include FontKit %>
    <% include CustomStyles %>
    <style>
        /* Screenshot capture styling */
        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .template-preview-container {
            /* Full width to allow desktop breakpoints to apply */
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
            box-sizing: border-box;
        }
    </style>
</head>
<body class="template-preview-content BlockPage">
    <main>
        <div class="template-preview-container" id="template-content">
            $Page.ElementalArea
        </div>
    </main>
</body>
</html>
