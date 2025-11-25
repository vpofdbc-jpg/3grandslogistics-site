// lib/widgets/vwc_form_widget.dart

import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';

// Updated imports to reflect 'firestore_test_app' package
import 'package:firestore_test_app/widgets/pricing_plan_selection.dart'; // Import to use PricingPlan enum
import 'package:firestore_test_app/screens/dashboard_screen.dart'; // <--- IMPORT CENTRALIZED APPSETTINGS


class VwcFormWidget extends StatefulWidget {
  final String currentUserId;
  final PricingPlan currentUserPlan;
  final AppSettings appSettings;
  final ValueChanged<String> onStatusUpdate;

  const VwcFormWidget({
    super.key,
    required this.currentUserId,
    required this.currentUserPlan,
    required this.appSettings,
    required this.onStatusUpdate,
  });

  @override
  State<VwcFormWidget> createState() => _VwcFormWidgetState();
}

class _VwcFormWidgetState extends State<VwcFormWidget> {
  final _formKey = GlobalKey<FormState>();
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;

  final TextEditingController _vendorNameController = TextEditingController();
  final TextEditingController _orderNumberController = TextEditingController();
  final TextEditingController _trackingNumberController = TextEditingController();

  DateTime? _selectedPreferredDeliveryDate;
  String? _selectedPreferredDeliveryWindow;

  double _deliveryCost = 0.00;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _updateCostDisplay();
  }

  @override
  void dispose() {
    _vendorNameController.dispose();
    _orderNumberController.dispose();
    _trackingNumberController.dispose();
    super.dispose();
  }

  void _updateCostDisplay() {
    setState(() {
      if (widget.currentUserPlan == PricingPlan.subscription) {
        _deliveryCost = 0.00; // Covered by subscription
      } else {
        _deliveryCost = widget.appSettings.baseDeliveryFee * widget.appSettings.surgeMultiplier;
      }
    });
  }

  Future<void> _selectPreferredDeliveryDate(BuildContext context) async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedPreferredDeliveryDate ?? DateTime.now(),
      firstDate: DateTime.now(),
      lastDate: DateTime(2101),
    );
    if (picked != null && picked != _selectedPreferredDeliveryDate) {
      setState(() {
        _selectedPreferredDeliveryDate = picked;
      });
    }
  }

  Future<void> _submitOrder() async {
    if (!_formKey.currentState!.validate()) {
      widget.onStatusUpdate('Please correct the highlighted form errors.');
      return;
    }

    if (_selectedPreferredDeliveryDate == null || _selectedPreferredDeliveryWindow == null) {
      widget.onStatusUpdate('Please select all required date and time options.');
      return;
    }

    print('VWC Submit: Starting submission, _isSubmitting = true'); // Added log
    setState(() {
      _isSubmitting = true;
      widget.onStatusUpdate('Submitting order...');
    });

    try {
      print('VWC Submit: Attempting Firestore add...'); // Added log
      await _firestore.collection('orders').add({
        'userId': widget.currentUserId,
        'serviceType': 'vendor_warehouse',
        'pricingPlanUsed': widget.currentUserPlan.name,
        'vendorName': _vendorNameController.text,
        'orderNumber': _orderNumberController.text,
        'trackingNumber': _trackingNumberController.text,
        'warehouseAddress': widget.appSettings.warehouseAddress,
        'preferredDeliveryDate': _selectedPreferredDeliveryDate!.toIso8601String().split('T')[0],
        'preferredDeliveryWindow': _selectedPreferredDeliveryWindow,
        'status': 'pending',
        'deliveryFee': _deliveryCost,
        'surgeApplied': widget.appSettings.surgeMultiplier > 1,
        'createdAt': FieldValue.serverTimestamp(),
      });
      print('VWC Submit: Firestore add successful!'); // Added log
      widget.onStatusUpdate('Vendor-to-Warehouse order placed successfully! Cost: \$${_deliveryCost.toStringAsFixed(2)}');
      _formKey.currentState?.reset();
      _vendorNameController.clear();
      _orderNumberController.clear();
      _trackingNumberController.clear();
      setState(() {
        _selectedPreferredDeliveryDate = null;
        _selectedPreferredDeliveryWindow = null;
        _deliveryCost = 0.00; // Reset
      });
      _updateCostDisplay(); // Recalculate after reset
    } catch (error) {
      print("VWC Submit: Error placing VWC order: $error"); // Added log
      widget.onStatusUpdate('Error: Failed to place order. ${error.toString()}');
    } finally {
      print('VWC Submit: Finally block executed, _isSubmitting = false'); // Added log
      setState(() {
        _isSubmitting = false;
      });
    }
  }

  @override
  void didUpdateWidget(covariant VwcFormWidget oldWidget) {
    super.didUpdateWidget(oldWidget);
    // If the pricing plan or app settings change, update the cost display
    if (widget.currentUserPlan != oldWidget.currentUserPlan ||
        widget.appSettings != oldWidget.appSettings) { // Compare instances of AppSettings
      _updateCostDisplay();
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

    return Card(
      color: colorScheme.surface,
      elevation: 2.0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12.0)),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // *** INCREASED FONT SIZE AND WEIGHT FOR "Vendor-to-Warehouse Details" HEADING ***
              Padding(
                padding: const EdgeInsets.only(bottom: 16.0, top: 8.0), // Added top padding
                child: Text(
                  'Vendor-to-Warehouse Details',
                  style: TextStyle(
                    fontSize: 20.0, // Increased from 18.0
                    fontWeight: FontWeight.bold, // Increased from w600
                    color: colorScheme.onBackground,
                  ),
                ),
              ),
              const Divider(height: 16, thickness: 1), // Adjusted height for spacing

              // Warehouse Address Display
              Container(
                margin: const EdgeInsets.only(bottom: 16.0),
                padding: const EdgeInsets.all(16.0),
                decoration: BoxDecoration(
                  color: colorScheme.tertiaryContainer,
                  borderRadius: BorderRadius.circular(8.0),
                  border: Border(
                    left: BorderSide(
                      color: colorScheme.tertiary,
                      width: 4.0,
                    ),
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'IMPORTANT: Use this address when ordering from your vendor!',
                      style: TextStyle(
                        fontWeight: FontWeight.w500,
                        color: colorScheme.onTertiaryContainer,
                      ),
                    ),
                    const SizedBox(height: 8.0),
                    Container(
                      padding: const EdgeInsets.all(4.0),
                      decoration: BoxDecoration(
                        color: colorScheme.onTertiaryContainer.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(4.0),
                      ),
                      child: Text(
                        'Warehouse Address: ${widget.appSettings.warehouseAddress}',
                        style: TextStyle(
                          fontFamily: 'monospace',
                          fontSize: 12.0,
                          color: colorScheme.onTertiaryContainer,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // Vendor Name
              Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: TextFormField(
                  controller: _vendorNameController,
                  decoration: _formInputDecoration(
                    'Vendor Name',
                    hintText: 'e.g., Amazon, Target, HelloFresh',
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter vendor name';
                    }
                    return null;
                    },
                ),
              ),

              // Order Number / ID
              Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: TextFormField(
                  controller: _orderNumberController,
                  decoration: _formInputDecoration(
                    'Order Number / ID',
                    hintText: 'e.g., #234-5678-ABCD',
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter order number';
                    }
                    return null;
                  },
                ),
              ),

              // Tracking Number
              Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: TextFormField(
                  controller: _trackingNumberController,
                  decoration: _formInputDecoration(
                    'Tracking Number',
                    hintText: 'e.g., 9400109699939126298516',
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter tracking number';
                    }
                    return null;
                  },
                ),
              ),

              // Preferred Delivery Date (Date Picker)
              Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: InkWell(
                  onTap: _isSubmitting ? null : () => _selectPreferredDeliveryDate(context),
                  child: InputDecorator(
                    decoration: _formInputDecoration('Preferred Delivery Date'),
                    baseStyle: TextStyle(color: colorScheme.onSurface),
                    child: Text(
                      _selectedPreferredDeliveryDate == null
                          ? 'Select a date'
                          : _selectedPreferredDeliveryDate!.toLocal().toIso8601String().split('T')[0],
                      style: TextStyle(color: colorScheme.onSurface, fontSize: 16),
                    ),
                  ),
                ),
              ),

              // Preferred Delivery Window (Dropdown)
              Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: DropdownButtonFormField<String>(
                  value: _selectedPreferredDeliveryWindow,
                  decoration: _formInputDecoration('Preferred Delivery Window'),
                  hint: Text('Select a time window', style: TextStyle(color: colorScheme.outline)),
                  items: <String>['1:00 PM - 4:00 PM', '4:00 PM - 7:00 PM', '7:00 PM - 10:00 PM']
                      .map<DropdownMenuItem<String>>((String value) {
                    return DropdownMenuItem<String>(
                      value: value,
                      // *** FIX: Removed Expanded, use overflow: TextOverflow.ellipsis ***
                      child: Text(value, overflow: TextOverflow.ellipsis),
                    );
                  }).toList(),
                  onChanged: _isSubmitting
                      ? null
                      : (String? newValue) {
                          setState(() {
                            _selectedPreferredDeliveryWindow = newValue;
                          });
                        },
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please select a delivery window';
                    }
                    return null;
                  },
                ),
              ),

              // VWC Delivery Cost Display
              Container(
                margin: const EdgeInsets.only(top: 16.0),
                padding: const EdgeInsets.all(16.0),
                decoration: BoxDecoration(
                  color: colorScheme.primaryContainer,
                  borderRadius: BorderRadius.circular(12.0),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'VWC Delivery Cost:',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: colorScheme.onPrimaryContainer,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '\$${_deliveryCost.toStringAsFixed(2)}',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 30.0,
                        fontWeight: FontWeight.bold,
                        color: colorScheme.primary,
                      ),
                    ),
                    Text(
                      widget.currentUserPlan == PricingPlan.subscription
                          ? 'Covered by your monthly VWC Subscription.'
                          : 'Pay-as-you-go price per delivery.',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 10.0, color: colorScheme.onPrimaryContainer),
                    ),
                  ],
                ),
              ),

              // Submit Button
              Padding(
                padding: const EdgeInsets.only(top: 24.0),
                child: ElevatedButton(
                  onPressed: _isSubmitting ? null : _submitOrder,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: colorScheme.primary,
                    foregroundColor: colorScheme.onPrimary,
                    padding: const EdgeInsets.symmetric(vertical: 16.0),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8.0),
                    ),
                    textStyle: const TextStyle(
                      fontSize: 16.0,
                      fontWeight: FontWeight.w600,
                    ),
                    minimumSize: const Size.fromHeight(50),
                  ),
                  child: _isSubmitting
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text('Request Vendor Service'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
