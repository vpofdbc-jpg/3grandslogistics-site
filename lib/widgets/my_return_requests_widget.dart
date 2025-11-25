// lib/widgets/my_return_requests_widget.dart

import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:intl/intl.dart'; // For date formatting

class MyReturnRequestsWidget extends StatelessWidget {
  final String userId;

  const MyReturnRequestsWidget({super.key, required this.userId});

  // Helper to get a color for the status for better visual cue
  Color _getStatusColor(String status) {
    switch (status) {
      case 'pending_approval':
        return Colors.orange.shade700;
      case 'approved':
        return Colors.green.shade700;
      case 'declined':
        return Colors.red.shade700;
      case 'pickup_scheduled':
        return Colors.blue.shade700;
      case 'completed':
        return Colors.grey.shade700;
      default:
        return Colors.grey.shade500;
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 16.0),
          child: Text(
            'My Return Requests',
            style: TextStyle(
              fontSize: 22.0,
              fontWeight: FontWeight.bold,
              color: colorScheme.onBackground,
            ),
          ),
        ),
        StreamBuilder<QuerySnapshot>(
          stream: FirebaseFirestore.instance
              .collection('returnRequests')
              .where('userId', isEqualTo: userId)
              .orderBy('requestDate', descending: true)
              .limit(5) // Show only the 5 most recent return requests
              .snapshots(),
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return Center(child: CircularProgressIndicator(color: colorScheme.primary));
            }
            if (snapshot.hasError) {
              return Center(
                  child: Text(
                'Error loading return requests: ${snapshot.error}',
                style: TextStyle(color: colorScheme.error),
              ));
            }
            if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
              return Center(
                  child: Text(
                'No recent return requests found.',
                style: TextStyle(color: colorScheme.onBackground.withOpacity(0.7)),
              ));
            }

            return ListView.builder(
              shrinkWrap: true, // Important for nested ListView in SingleChildScrollView
              physics: const NeverScrollableScrollPhysics(), // Disable scrolling for this inner list
              itemCount: snapshot.data!.docs.length,
              itemBuilder: (context, index) {
                var request = snapshot.data!.docs[index];
                Map<String, dynamic> requestData = request.data() as Map<String, dynamic>;

                String formattedRequestDate = 'N/A';
                if (requestData['requestDate'] is Timestamp) {
                  formattedRequestDate = DateFormat('MMM dd, yyyy HH:mm')
                      .format((requestData['requestDate'] as Timestamp).toDate());
                }

                String status = requestData['status'] ?? 'unknown';
                String reasonCategory = requestData['reasonCategory'] ?? 'N/A';
                String originalOrderId = requestData['orderId'] ?? 'N/A';
                String pickupAddress = requestData['pickupAddress'] ?? 'N/A';

                return Card(
                  margin: const EdgeInsets.only(bottom: 12.0),
                  elevation: 2,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12.0)),
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Return ID: ${request.id}',
                          style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 16.0,
                              color: colorScheme.onSurface),
                        ),
                        const SizedBox(height: 8),
                        Text('Original Order: $originalOrderId',
                            style: TextStyle(color: colorScheme.onSurfaceVariant)),
                        Text('Reason: $reasonCategory',
                            style: TextStyle(color: colorScheme.onSurfaceVariant)),
                        Text('Status: $status',
                            style: TextStyle(
                                fontWeight: FontWeight.w500,
                                color: _getStatusColor(status))), // Dynamic status color
                        Text('Pickup Address: $pickupAddress',
                            style: TextStyle(color: colorScheme.onSurfaceVariant)),
                        Text('Requested On: $formattedRequestDate',
                            style: TextStyle(color: colorScheme.onSurfaceVariant)),
                      ],
                    ),
                  ),
                );
              },
            );
          },
        ),
      ],
    );
  }
}
