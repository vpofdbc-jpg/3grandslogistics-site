// lib/widgets/my_orders_widget.dart

import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:intl/intl.dart'; // For date formatting

// Import the new return request form widget
import 'package:firestore_test_app/widgets/return_request_form_widget.dart';
import 'package:firestore_test_app/screens/order_details_screen.dart';

class MyOrdersWidget extends StatelessWidget {
  final String userId;

  const MyOrdersWidget({super.key, required this.userId});

  // Helper function to stream active return request status for an order
  // This version explicitly looks for a set of 'active' statuses.
  Stream<String?> _streamActiveReturnRequestStatus(String orderId) {
    print('DEBUG: Setting up stream for active return requests for Order ID: $orderId, User ID: $userId');

    return FirebaseFirestore.instance
        .collection('returnRequests')
        .where('orderId', isEqualTo: orderId)
        .where('userId', isEqualTo: userId)
        // Explicitly look for statuses that indicate an ongoing request
        .where('status', whereIn: ['pending_approval', 'approved', 'pickup_scheduled'])
        .limit(1) // Only need to find one to know an active request exists
        .snapshots() // This makes it a stream!
        .map((querySnapshot) {
          if (querySnapshot.docs.isNotEmpty) {
            final status = querySnapshot.docs.first.data()['status'] as String?;
            print('DEBUG: Stream emitted ACTIVE return request for $orderId with status: $status');
            return status;
          }
          print('DEBUG: Stream emitted NO ACTIVE return request found for $orderId.');
          return null;
        });
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
            'My Recent Orders',
            style: TextStyle(
              fontSize: 22.0,
              fontWeight: FontWeight.bold,
              color: colorScheme.onBackground,
            ),
          ),
        ),
        StreamBuilder<QuerySnapshot>(
          stream: FirebaseFirestore.instance
              .collection('orders')
              .where('userId', isEqualTo: userId)
              .orderBy('createdAt', descending: true)
              .limit(5) // Show only the 5 most recent orders
              .snapshots(),
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return Center(child: CircularProgressIndicator(color: colorScheme.primary));
            }
            if (snapshot.hasError) {
              return Center(
                  child: Text(
                'Error loading orders: ${snapshot.error}',
                style: TextStyle(color: colorScheme.error),
              ));
            }
            if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
              return Center(
                  child: Text(
                'No recent orders found.',
                style: TextStyle(color: colorScheme.onBackground.withOpacity(0.7)),
              ));
            }

            return ListView.builder(
              shrinkWrap: true, // Important for nested ListView in SingleChildScrollView
              physics: const NeverScrollableScrollPhysics(), // Disable scrolling for this inner list
              itemCount: snapshot.data!.docs.length,
              itemBuilder: (context, index) {
                var order = snapshot.data!.docs[index];
                Map<String, dynamic> orderData = order.data() as Map<String, dynamic>;

                // Safely format timestamp
                String formattedDate = 'N/A';
                if (orderData['createdAt'] is Timestamp) {
                  formattedDate = DateFormat('MMM dd, yyyy HH:mm')
                      .format((orderData['createdAt'] as Timestamp).toDate());
                }

                String serviceType = orderData['serviceType'] == 'on_demand'
                    ? 'On-Demand'
                    : 'Vendor-Warehouse';
                String orderStatus = orderData['status'] ?? 'unknown'; // Renamed to avoid clash

                double deliveryFee = orderData['deliveryFee'] ?? 0.0;

                // Determine if a return button should be shown
                bool isEligibleForReturn = orderStatus == 'completed';

                return Card(
                  margin: const EdgeInsets.only(bottom: 12.0),
                  elevation: 2,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12.0)),
                  child: InkWell(
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (ctx) => OrderDetailsScreen(orderId: order.id),
                        ),
                      );
                    },
                    borderRadius: BorderRadius.circular(12.0),
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Order ID: ${order.id}',
                            style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 16.0,
                                color: colorScheme.onSurface),
                          ),
                          const SizedBox(height: 8),
                          Text('Service Type: $serviceType',
                              style: TextStyle(color: colorScheme.onSurfaceVariant)),
                          Text('Status: $orderStatus',
                              style: TextStyle(
                                  color: orderStatus == 'pending'
                                      ? Colors.orange
                                      : orderStatus == 'completed'
                                          ? Colors.green
                                          : colorScheme.onSurfaceVariant)),
                          Text('Delivery Fee: \$${deliveryFee.toStringAsFixed(2)}',
                              style: TextStyle(color: colorScheme.onSurfaceVariant)),
                          Text('Placed On: $formattedDate',
                              style: TextStyle(color: colorScheme.onSurfaceVariant)),
                          // Add more order details as needed

                          if (isEligibleForReturn) ...[
                            const Divider(height: 24, thickness: 1),
                            // *** MODIFIED: Now StreamBuilder to listen for changes ***
                            StreamBuilder<String?>(
                              stream: _streamActiveReturnRequestStatus(order.id), // <--- Use the new stream!
                              builder: (context, returnRequestSnapshot) {
                                // This state handles the very initial moment of stream connection.
                                // If it's still waiting or has an error, show a placeholder.
                                if (returnRequestSnapshot.connectionState == ConnectionState.waiting) {
                                  return Align(
                                    alignment: Alignment.centerRight,
                                    child: SizedBox(
                                      height: 20, width: 20,
                                      child: CircularProgressIndicator(
                                          strokeWidth: 2, color: colorScheme.primary),
                                    ),
                                  );
                                }
                                // If there's an error in the stream itself
                                if (returnRequestSnapshot.hasError) {
                                  print('Error in return request stream: ${returnRequestSnapshot.error}');
                                  return const Align(
                                    alignment: Alignment.centerRight,
                                    child: Text(
                                      'Error loading return status',
                                      style: TextStyle(color: Colors.red),
                                    ),
                                  );
                                }

                                final existingReturnStatus = returnRequestSnapshot.data;

                                if (existingReturnStatus != null) {
                                  // An active return request already exists
                                  String displayStatus = existingReturnStatus.replaceAll('_', ' '); // Make it readable
                                  return Align(
                                    alignment: Alignment.centerRight,
                                    child: Text(
                                      'Return: ${displayStatus.toUpperCase()}',
                                      style: TextStyle(
                                        color: Colors.blueGrey,
                                        fontWeight: FontWeight.w600,
                                        fontSize: 12,
                                      ),
                                    ),
                                  );
                                } else {
                                  // No active return request, show the button
                                  return Align(
                                    alignment: Alignment.centerRight,
                                    child: OutlinedButton.icon(
                                      icon: const Icon(Icons.undo_rounded),
                                      label: const Text('Request Return'),
                                      onPressed: () {
                                        showModalBottomSheet(
                                          context: context,
                                          isScrollControlled: true,
                                          backgroundColor: Colors.transparent,
                                          builder: (context) {
                                            return Padding(
                                              padding: EdgeInsets.only(
                                                bottom: MediaQuery.of(context).viewInsets.bottom,
                                              ),
                                              child: DraggableScrollableSheet(
                                                initialChildSize: 0.9,
                                                minChildSize: 0.5,
                                                maxChildSize: 0.95,
                                                expand: false,
                                                builder: (_, controller) {
                                                  return Container(
                                                    decoration: BoxDecoration(
                                                      color: colorScheme.background,
                                                      borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
                                                    ),
                                                    child: ClipRRect(
                                                      borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
                                                      child: ReturnRequestFormWidget(
                                                        originalOrderId: order.id,
                                                        originalOrderData: orderData,
                                                        scrollController: controller,
                                                      ),
                                                    ),
                                                  );
                                                },
                                              ),
                                            );
                                          },
                                        );
                                      },
                                      style: OutlinedButton.styleFrom(
                                        foregroundColor: colorScheme.primary,
                                        side: BorderSide(color: colorScheme.primary),
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8.0)),
                                      ),
                                    ),
                                  );
                                }
                              },
                            ),
                          ],
                        ],
                      ),
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
