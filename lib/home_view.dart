import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';

class HomeView extends StatelessWidget {
  const HomeView({super.key});

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final user = FirebaseAuth.instance.currentUser;
    
    return Scaffold(
      appBar: AppBar(
        title: const Text('Home Portal'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => FirebaseAuth.instance.signOut(),
            tooltip: 'Sign Out',
          ),
        ],
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.check_circle, size: 80, color: colorScheme.primary),
              const SizedBox(height: 20),
              Text(
                'Authentication Successful!',
                style: TextStyle(
                  fontSize: 24, 
                  fontWeight: FontWeight.bold,
                  color: colorScheme.onBackground,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                'Welcome, Guest User.',
                style: TextStyle(fontSize: 18, color: colorScheme.onBackground),
              ),
              const SizedBox(height: 20),
              Text(
                'You are currently signed in with ID:',
                textAlign: TextAlign.center,
                style: TextStyle(color: colorScheme.outline),
              ),
              Text(
                user?.uid ?? 'N/A',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 14, 
                  fontWeight: FontWeight.w500,
                  color: colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 30),
              // Placeholder button for future feature development
              ElevatedButton.icon(
                onPressed: () {
                  // This is where you will navigate to the Dashboard later
                },
                icon: const Icon(Icons.construction),
                label: const Text('Dashboard Under Construction'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: colorScheme.tertiary, // Use accent color
                  foregroundColor: colorScheme.onTertiary, 
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
                ),
              )
            ],
          ),
        ),
      ),
    );
  }
}