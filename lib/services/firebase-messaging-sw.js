// web/firebase-messaging-sw.js

// Import the Firebase JS SDK for Firebase products that you want to use
// The Firebase JS SDK must be available as a service worker script.
importScripts('https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.1/firebase-messaging-compat.js');

// TODO: Replace the following with your app's Firebase project configuration
// For Firebase JavaScript SDK v7.20.0 and later, `measurementId` is an optional field
// (copied from your firebase_options.dart for web)
const firebaseConfig = {
  apiKey: "YOUR_API_KEY_HERE", // Replace with your actual API key
  authDomain: "grandslogistics-8af41.firebaseapp.com",
  projectId: "grandslogistics-8af41",
  storageBucket: "grandslogistics-8af41.appspot.com",
  messagingSenderId: "267189664335", // This is your project's Sender ID
  appId: "1:267189664335:web:bf0536ef521ace642e883a",
  measurementId: "G-YOUR_MEASUREMENT_ID_HERE" // Replace if you have GA configured
};

// Initialize Firebase
const app = firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

// Optional: Handle background messages
// This function is executed when a message is received while your web app is not in focus.
messaging.onBackgroundMessage((payload) => {
  console.log('[firebase-messaging-sw.js] Received background message (SHOWING NOW!)', payload);

  // Customize notification here
  const notificationTitle = payload.notification.title || 'Background Message Title';
  const notificationOptions = {
    body: payload.notification.body || 'Background Message Body',
    // The problematic 'icon' line is REMOVED for guaranteed display.
    // If you add an icon later, ensure it's a valid path like '/firebase-logo.png'
    // and the image file actually exists at that root path in your 'web' folder.
  };

  self.registration.showNotification(notificationTitle, notificationOptions);
});
