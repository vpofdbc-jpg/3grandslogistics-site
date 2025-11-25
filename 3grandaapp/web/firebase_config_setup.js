// This script ensures the Firebase configuration and auth token are available globally
// for the main application (e.g., Flutter) to use during initialization.

// Check if the required global variables from the environment exist.
if (typeof __firebase_config !== 'undefined') {
    // Expose the parsed configuration object globally.
    // Your main application should read from window.FIREBASE_CONFIG_OBJECT
    window.FIREBASE_CONFIG_OBJECT = JSON.parse(__firebase_config);
    console.log("Firebase configuration loaded successfully.");
} else {
    // If the config is missing, define it as null to trigger the error display
    window.FIREBASE_CONFIG_OBJECT = null;
    console.error("Critical Error: __firebase_config is undefined in the environment.");
}

if (typeof __initial_auth_token !== 'undefined') {
    // Expose the initial auth token globally.
    // Your main application should read from window.FIREBASE_AUTH_TOKEN
    window.FIREBASE_AUTH_TOKEN = __initial_auth_token;
    console.log("Initial authentication token loaded.");
} else {
    window.FIREBASE_AUTH_TOKEN = null;
    console.warn("Warning: __initial_auth_token is undefined. The app will need to sign in anonymously.");
}

// If the essential config is missing, override the body to show the error message gracefully
if (!window.FIREBASE_CONFIG_OBJECT) {
    document.addEventListener('DOMContentLoaded', () => {
        const errorHtml = `
            <div style="position:fixed; top:0; left:0; width:100%; height:100%; background-color:#fef2f2; color:#b91c1c; display:flex; justify-content:center; align-items:center; z-index:9999; font-family: sans-serif;">
                <div style="padding: 2rem; max-width: 400px; border: 1px solid #fca5a5; border-radius: 0.5rem; background-color: #fee2e2; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                    <h1 style="font-size: 1.5rem; margin-bottom: 1rem;">Configuration Error</h1>
                    <p>Firebase config is missing. Please ensure the environment provides <code>__firebase_config</code>.</p>
                    <p style="font-size: 0.875rem; color: #991b1b; margin-top: 1rem;">The application cannot start without the required Firebase settings.</p>
                </div>
            </div>
        `;
        document.body.innerHTML = errorHtml;
    });
}