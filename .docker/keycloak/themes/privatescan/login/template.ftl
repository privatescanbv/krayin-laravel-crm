<#macro registrationLayout
    displayMessage=false
    displayInfo=false
    displayRequiredFields=false
    section=""
>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>${msg("loginTitle")}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="${url.resourcesPath}/css/style.css">
</head>

<body>

    <#-- Zorg dat section nooit null is -->
    <#assign _section = section!"form">

    <#nested _section>

</body>
</html>
</#macro>
