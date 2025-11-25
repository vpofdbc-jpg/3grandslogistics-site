import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
// FIX: Using the user's project name 'firestore_test_app'
import 'package:firestore_test_app/firebase_options.dart'; 
import 'package:firestore_test_app/screens/splash_screen.dart';

// --- Define your custom Color Scheme ---
// Mapped from your Afrocentric CSS variables
final ColorScheme _afrocentricColorScheme = const ColorScheme.light().copyWith(
  // Primary Colors (Deep Forest Green)
  primary: const Color(0xFF3A6B35), // --primary-color
  onPrimary: Colors.white, // Text on primary (ensure contrast)
  primaryContainer: const Color(0xFFD1E8C9), // --primary-light
  onPrimaryContainer: const Color(0xFF254E20), // --primary-dark (text on primaryContainer)

  // Secondary Colors (Earthy Brown/Gray for general elements)
  secondary: const Color(0xFFB3A68F), // --gray-300
  onSecondary: const Color(0xFF211A13), // --gray-800 (text on secondary)
  secondaryContainer: const Color(0xFFD4C9A9), // --gray-200 (lighter earthy beige)
  onSecondaryContainer: const Color(0xFF4A3F35), // --gray-600 (text on secondaryContainer)

  // Tertiary Colors (Goldenrod, for accents)
  tertiary: const Color(0xFFDAA520), // --yellow-400
  onTertiary: const Color(0xFF8B5E00), // --yellow-800 (text on tertiary)
  tertiaryContainer: const Color(0xFFFCE8A1), // --yellow-100
  onTertiaryContainer: const Color(0xFF8B5E00), // --yellow-800 (text on tertiaryContainer)

  // Error Colors (Rich Earthy Red)
  error: const Color(0xFFDD2C00), // --danger-color
  onError: Colors.white,
  errorContainer: const Color(0xFFFFDAD6), // A default Material 3 light error container
  onErrorContainer: const Color(0xFFB91C1C), // A default Material 3 dark text on error container

  // Background and Surface Colors (Soft Cream/Earthy Browns)
  background: const Color(0xFFF8F4E3), // --gray-bg-page
  onBackground: const Color(0xFF211A13), // --gray-800 (text on background)
  surface: const Color(0xFFF8F4E3), // --gray-bg-page (for cards, etc.)
  onSurface: const Color(0xFF211A13), // --gray-800 (text on surface)
  surfaceVariant: const Color(0xFFD4C9A9), // --gray-200 (for subtle borders/dividers)
  onSurfaceVariant: const Color(0xFF4A3F35), // --gray-600 (text on surfaceVariant)

  // Other important Material 3 colors
  outline: const Color(0xFF8C7D6B), // --gray-400
  shadow: Colors.black.withOpacity(0.1), // Default shadow
  inverseSurface: const Color(0xFF211A13), // Inverse of surface (for dark mode equivalent)
  onInverseSurface: const Color(0xFFF8F4E3),
  inversePrimary: const Color(0xFFD1E8C9), // Contrast to primary
  scrim: Colors.black.withOpacity(0.5), // For modal overlays
);
// ------------------------------------

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: '3 Grands Logistics Customer App',
      theme: ThemeData(
        useMaterial3: true, // Enable Material 3 features
        colorScheme: _afrocentricColorScheme, // Apply your custom ColorScheme
        scaffoldBackgroundColor: _afrocentricColorScheme.background, // Match your body background
        appBarTheme: AppBarTheme(
          backgroundColor: _afrocentricColorScheme.surface, // Use surface for AppBar background
          foregroundColor: _afrocentricColorScheme.onSurface, // Text/icon color on AppBar
          elevation: 2.0, // Slight shadow
          titleTextStyle: TextStyle(
            color: _afrocentricColorScheme.onSurface,
            fontSize: 20.0,
            fontWeight: FontWeight.bold,
          ),
        ),
        visualDensity: VisualDensity.adaptivePlatformDensity,
      ),
      home: const SplashScreen(), // Starting with the Splash Screen
    );
  }
}