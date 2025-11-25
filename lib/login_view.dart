import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'home_view.dart'; // Import the HomeView placeholder

// LoginView: The view displayed when the user is not authenticated.
class LoginView extends StatelessWidget {
  const LoginView({super.key});

  Future<void> _signInAnonymously(BuildContext context) async {
    try {
      // Use signInAnonymously for quick testing, replace with email/password later
      await FirebaseAuth.instance.signInAnonymously();
      // If successful, navigate to HomeView after sign-in.
      if (context.mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (context) => const HomeView()),
        );
      }
    } on FirebaseAuthException catch (e) {
      // Display the error code and message for debugging.
      String errorMessage = 'Auth Failed: ${e.code}\n${e.message}';
      
      // Use a SnackBar to display the error
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(errorMessage),
          duration: const Duration(seconds: 4),
          backgroundColor: Colors.redAccent,
        ),
      );
    } catch (e) {
      // Handle non-FirebaseAuth exceptions (e.g., network issues)
       ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('An unexpected error occurred: $e'),
          duration: const Duration(seconds: 4),
          backgroundColor: Colors.redAccent,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    // Access the custom color scheme
    final colorScheme = Theme.of(context).colorScheme;

    return Scaffold(
      // The AppBar now follows the custom theme defined in main.dart
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 400),
          // The central login card
          child: Card(
            elevation: 8,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            // Use the surface color from the theme (F8F4E3)
            color: colorScheme.surface, 
            margin: const EdgeInsets.all(32.0),
            child: Padding(
              padding: const EdgeInsets.all(32.0),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  // Logistics Icon, colored with the primary color
                  Icon(
                    Icons.local_shipping, // A professional logistics icon
                    size: 72,
                    color: colorScheme.primary, // Deep Forest Green
                  ),
                  const SizedBox(height: 24),
                  // Title
                  Text(
                    'Welcome to the Logistics Portal',
                    style: TextStyle(
                      fontSize: 24, 
                      fontWeight: FontWeight.bold,
                      color: colorScheme.onSurface, // Text color for surface
                    ),
                  ),
                  const SizedBox(height: 8),
                  // Subtitle
                  Text(
                    'Access your secure shipment and tracking data.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 16, 
                      color: colorScheme.onSurfaceVariant, // Earthy Grey for subtitle
                    ),
                  ),
                  const SizedBox(height: 40),
                  // Login Button
                  ElevatedButton.icon(
                    onPressed: () => _signInAnonymously(context),
                    icon: const Icon(Icons.security, size: 20),
                    label: const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 10, vertical: 12),
                      child: Text(
                        'Continue as Guest', 
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: colorScheme.primary, // Deep Forest Green
                      foregroundColor: colorScheme.onPrimary, // White text
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      minimumSize: const Size(double.infinity, 50), // Full width button
                      elevation: 4,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Your user ID will be generated automatically.',
                    style: TextStyle(fontSize: 12, color: colorScheme.outline), // Use outline color for subtle text
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}