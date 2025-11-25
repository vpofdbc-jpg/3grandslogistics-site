import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart'; // Added for defaultTargetPlatform

class FirebaseNotificationService {
  final FirebaseMessaging _fcm;
  final FirebaseAuth _auth;
  final FirebaseFirestore _firestore;

  // Add a private variable to store the latest FCM token
  String? _currentFCMToken;

  /// Public getter to access the latest FCM token
  String? get fcmToken => _currentFCMToken;

  /// Constructor to inject Firebase instances.
  /// This ensures that FirebaseFirestore.instance is accessed AFTER
  /// any necessary setup (like disablePersistence()) in main.dart.
  FirebaseNotificationService({
    FirebaseMessaging? fcm,
    FirebaseAuth? auth,
    FirebaseFirestore? firestore,
  })  : _fcm = fcm ?? FirebaseMessaging.instance,
        _auth = auth ?? FirebaseAuth.instance,
        _firestore = firestore ?? FirebaseFirestore.instance;

  /// Initializes notification permissions and sets up foreground/on-message listeners.
  Future<void> initNotifications() async {
    // Request permission (necessary for web/mobile)
    final settings = await _fcm.requestPermission(
      alert: true,
      announcement: false,
      badge: true,
      carPlay: false,
      criticalAlert: false,
      provisional: false,
      sound: true,
    );

    if (kDebugMode) {
      print('DEBUG FCM: User granted permission: ${settings.authorizationStatus}');
    }

    // Get the initial token and store it
    String? token = await _fcm.getToken();
    _currentFCMToken = token; // Store the fetched token
    if (kDebugMode) {
      print('DEBUG FCM: FCM Token: $token');
    }

    // Listen for token refresh and update _currentFCMToken and save to Firestore
    _fcm.onTokenRefresh.listen((newToken) {
      _currentFCMToken = newToken;
      if (kDebugMode) {
        print('DEBUG FCM: FCM token refreshed to: $newToken');
      }
      saveTokenForUser(); // Save the new token to Firestore
    });

    // Handle messages when the app is in the foreground (open)
    FirebaseMessaging.onMessage.listen(_handleMessage);

    // If a message opens the app from terminated or background state
    FirebaseMessaging.onMessageOpenedApp.listen(_handleMessageOpenedApp);
  }

  /// Handles an incoming FCM message while the app is in the foreground.
  void _handleMessage(RemoteMessage message) {
    if (kDebugMode) {
      print('DEBUG FCM: Got a message whilst in the foreground!');
      print('DEBUG FCM: Message data: ${message.data}');
    }
    // TODO: Show a local notification here (e.g., using flutter_local_notifications)
  }

  /// Handles an incoming FCM message that caused the app to be opened.
  void _handleMessageOpenedApp(RemoteMessage message) {
    if (kDebugMode) {
      print('DEBUG FCM: Message opened app: ${message.data}');
    }
    // TODO: Navigate the user to a specific screen here based on message.data
  }

  /// Saves the current user's FCM token to Firestore.
  /// This is called in main.dart every time a user signs in.
  Future<void> saveTokenForUser() async {
    final user = _auth.currentUser;
    // Use the stored token; if for some reason it's null, try to get it again.
    final token = _currentFCMToken ?? await _fcm.getToken();

    // CRITICAL GUARD CLAUSES: Prevent token save if auth state is pending or token is missing
    if (user == null || token == null) {
      if (kDebugMode) {
        print('ERROR FCM: User is null or token is null. Cannot save. User: ${user?.uid}, Token: $token');
      }
      return;
    }

    // Set the token data
    final tokenData = {
      'token': token,
      'createdAt': FieldValue.serverTimestamp(),
      'platform': defaultTargetPlatform.name,
      'isWeb': kIsWeb,
    };

    // Use a Set operation on a dedicated 'fcmTokens' sub-collection
    // The document ID is the token itself to prevent duplicates and handle token revocation.
    try {
      await _firestore
          .collection('users')
          .doc(user.uid)
          .collection('fcmTokens')
          .doc(token) // Document ID is the token string
          .set(tokenData, SetOptions(merge: true));

      if (kDebugMode) {
        print('SUCCESS FCM: Stored token $token for user ${user.uid}');
      }
    } catch (e) {
      if (kDebugMode) {
        print('ERROR FCM: Failed to store FCM token for user ${user.uid}: $e');
      }
    }
  }
}
