<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uitloggen...</title>
</head>
<body>
    <!-- Hidden iframe to logout from Keycloak -->
    <iframe src="{{ $logoutUrl }}" style="display: none;" id="keycloak-logout-iframe"></iframe>
    
    <script>
        // Redirect to login page immediately
        // The iframe will handle Keycloak logout in the background
        setTimeout(function() {
            window.location.href = '{{ $loginUrl }}';
        }, 500);
    </script>
    
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <p>Uitloggen...</p>
    </div>
</body>
</html>

