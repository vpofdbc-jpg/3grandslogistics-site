// lib/widgets/return_request_form_widget.dart

import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:intl/intl.dart';

class ReturnRequestFormWidget extends StatefulWidget {
  final String originalOrderId;
  final Map<String, dynamic> originalOrderData;
  final ScrollController scrollController; // For DraggableScrollableSheet

  const ReturnRequestFormWidget({
    super.key,
    required this.originalOrderId,
    required this.originalOrderData,
    required this.scrollController,
  });

  @override
  State<ReturnRequestFormWidget> createState() => _ReturnRequestFormWidgetState();
}

class _ReturnRequestFormWidgetState extends State<ReturnRequestFormWidget> {
  final _formKey = GlobalKey<FormState>();
  final _firestore = FirebaseFirestore.instance;
  final _auth = FirebaseAuth.instance;

  final TextEditingController _reasonController = TextEditingController();
  final TextEditingController _pickupAddressController = TextEditingController();

  String? _selectedReasonCategory;
  DateTime? _selectedPreferredPickupDate;
  String? _selectedPreferredPickupWindow;

  bool _isSubmitting = false;
  String? _statusMessage;

  @override
  void initState() {
    super.initState();
    // Pre-fill pickup address with original delivery address from the order
    _pickupAddressController.text = widget.originalOrderData['finalDeliveryAddress'] ?? '';
  }

  @override
  void dispose() {
    _reasonController.dispose();
    _pickupAddressController.dispose();
    super.dispose();
  }

  Future<void> _selectPreferredPickupDate(BuildContext context) async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedPreferredPickupDate ?? DateTime.now(),
      firstDate: DateTime.now(),
      lastDate: DateTime(2101),
    );
    if (picked != null && picked != _selectedPreferredPickupDate) {
      setState(() {
        _selectedPreferredPickupDate = picked;
      });
    }
  }

  Future<void> _submitReturnRequest() async {
    if (!_formKey.currentState!.validate()) {
      setState(() {
        _statusMessage = 'Please correct the highlighted form errors.';
      });
      return;
    }

    if (_selectedPreferredPickupDate == null || _selectedPreferredPickupWindow == null) {
      setState(() {
        _statusMessage = 'Please select a preferred pickup date and time window.';
      });
      return;
    }
    if (_selectedReasonCategory == null) {
      setState(() {
        _statusMessage = 'Please select a reason category for the return.';
      });
      return;
    }

    setState(() {
      _isSubmitting = true;
      _statusMessage = 'Submitting return request...';
    });

    try {
      final userId = _auth.currentUser?.uid;
      if (userId == null) {
        throw Exception('User not logged in.');
      }

      await _firestore.collection('returnRequests').add({
        'orderId': widget.originalOrderId,
        'userId': userId, // <--- CORRECTED SYNTAX FOR KEY-VALUE PAIR
        'requestDate': FieldValue.serverTimestamp(),
        'reasonCategory': _selectedReasonCategory, // Added category
        'reasonDetails': _reasonController.text,   // Detailed reason
        'status': 'pending_approval',
        'pickupAddress': _pickupAddressController.text,
        'preferredReturnPickupDate': DateFormat('yyyy-MM-dd').format(_selectedPreferredPickupDate!),
        'preferredReturnPickupWindow': _selectedPreferredPickupWindow,
        // Optionally include original order details for quick reference
        'originalServiceType': widget.originalOrderData['serviceType'],
        'originalDeliveryFee': widget.originalOrderData['deliveryFee'],
      });

      setState(() {
        _statusMessage = 'Return request submitted successfully! We will review it shortly.';
        _reasonController.clear();
        _selectedReasonCategory = null;
        _selectedPreferredPickupDate = null;
        _selectedPreferredPickupWindow = null;
      });
      // Close the bottom sheet after successful submission
      Navigator.of(context).pop();
    } catch (e) {
      print('Error submitting return request: $e');
      setState(() {
        // Display a user-friendly message indicating permission was denied
        if (e is FirebaseException && e.code == 'permission-denied') {
          _statusMessage = 'Permission denied. You cannot submit a return request for another user.';
        } else {
          _statusMessage = 'Error submitting request: ${e.toString()}';
        }
      });
    } finally {
      setState(() {
        _isSubmitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    InputDecoration _formInputDecoration(String labelText, {String? hintText}) {
      return InputDecoration(
        labelText: labelText,
        hintText: hintText,
        labelStyle: TextStyle(color: colorScheme.onBackground),
        hintStyle: TextStyle(color: colorScheme.outline),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8.0),
          borderSide: BorderSide(color: colorScheme.outline),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8.0),
          borderSide: BorderSide(color: colorScheme.primary, width: 2.0),
        ),
        filled: true,
        fillColor: colorScheme.surface,
      );
    }

    return Scaffold(
      backgroundColor: Colors.transparent, // To show the container's rounded corners
      body: SingleChildScrollView(
        controller: widget.scrollController, // Attach scroll controller
        padding: const EdgeInsets.all(24.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Request a Return',
                    style: TextStyle(
                      fontSize: 24.0,
                      fontWeight: FontWeight.bold,
                      color: colorScheme.onBackground,
                    ),
                  ),
                  IconButton(
                    icon: Icon(Icons.close, color: colorScheme.onBackground),
                    onPressed: () => Navigator.of(context).pop(), // Close the sheet
                  ),
                ],
              ),
              const Divider(height: 24, thickness: 1),
              Text(
                'Original Order ID: ${widget.originalOrderId}',
                style: TextStyle(
                  fontSize: 16.0,
                  fontWeight: FontWeight.w500,
                  color: colorScheme.onBackground,
                ),
              ),
              const SizedBox(height: 16),

              TextFormField(
                controller: _pickupAddressController,
                decoration: _formInputDecoration(
                  'Return Pickup Address',
                  hintText: 'Where should we pick up the item?',
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Please enter a pickup address for the return.';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),

              DropdownButtonFormField<String>(
                value: _selectedReasonCategory,
                decoration: _formInputDecoration('Reason for Return'),
                hint: Text('Select a reason category', style: TextStyle(color: colorScheme.outline)),
                items: <String>[
                  'Damaged Item',
                  'Wrong Item Delivered',
                  'Item No Longer Needed',
                  'Quality Issue',
                  'Other',
                ].map<DropdownMenuItem<String>>((String value) {
                  return DropdownMenuItem<String>(
                    value: value,
                    child: Text(value, overflow: TextOverflow.ellipsis),
                  );
                }).toList(),
                onChanged: (String? newValue) {
                  setState(() {
                    _selectedReasonCategory = newValue;
                  });
                },
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Please select a reason category.';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),

              TextFormField(
                controller: _reasonController,
                decoration: _formInputDecoration(
                  'Additional Details (Optional)',
                  hintText: 'e.g., Specific damage, wrong size, etc.',
                ),
                maxLines: 3,
              ),
              const SizedBox(height: 16),

              InkWell(
                onTap: _isSubmitting ? null : () => _selectPreferredPickupDate(context),
                child: InputDecorator(
                  decoration: _formInputDecoration('Preferred Return Pickup Date'),
                  baseStyle: TextStyle(color: colorScheme.onSurface),
                  child: Text(
                    _selectedPreferredPickupDate == null
                        ? 'Select a date'
                        : DateFormat('yyyy-MM-dd').format(_selectedPreferredPickupDate!),
                    style: TextStyle(color: colorScheme.onSurface, fontSize: 16),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              DropdownButtonFormField<String>(
                value: _selectedPreferredPickupWindow,
                decoration: _formInputDecoration('Preferred Return Pickup Window'),
                hint: Text('Select a time window', style: TextStyle(color: colorScheme.outline)),
                items: <String>['1:00 PM - 4:00 PM', '4:00 PM - 7:00 PM', '7:00 PM - 10:00 PM']
                    .map<DropdownMenuItem<String>>((String value) {
                  return DropdownMenuItem<String>(
                    value: value,
                    child: Text(value, overflow: TextOverflow.ellipsis),
                  );
                }).toList(),
                onChanged: (String? newValue) {
                  setState(() {
                    _selectedPreferredPickupWindow = newValue;
                  });
                },
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Please select a time window.';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 24),

              if (_statusMessage != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16.0),
                  child: Text(
                    _statusMessage!,
                    style: TextStyle(
                        color: _isSubmitting ? colorScheme.onBackground : colorScheme.error, // Show errors in red
                        fontWeight: FontWeight.w500),
                    textAlign: TextAlign.center,
                  ),
                ),

              ElevatedButton(
                onPressed: _isSubmitting ? null : _submitReturnRequest,
                style: ElevatedButton.styleFrom(
                  minimumSize: const Size.fromHeight(50),
                  backgroundColor: colorScheme.primary,
                  foregroundColor: colorScheme.onPrimary,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12.0)),
                  textStyle: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                child: _isSubmitting
                    ? CircularProgressIndicator(color: colorScheme.onPrimary)
                    : const Text('Submit Return Request'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
