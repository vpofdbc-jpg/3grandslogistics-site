// lib/widgets/on_demand_form_widget.dart

import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';

// Updated imports to reflect 'firestore_test_app' package
import 'package:firestore_test_app/widgets/pricing_plan_selection.dart'; // Import to use PricingPlan enum
import 'package:firestore_test_app/screens/dashboard_screen.dart';       // <--- IMPORT CENTRALIZED APPSETTINGS


class OnDemandFormWidget extends StatefulWidget {
  final String currentUserId;
  final PricingPlan currentUserPlan;
  final AppSettings appSettings;
  final ValueChanged<String> onStatusUpdate;

  const OnDemandFormWidget({
    super.key,
    required this.currentUserId,
    required this.currentUserPlan,
    required this.appSettings,
    required this.onStatusUpdate,
  });

  @override
  State<OnDemandFormWidget> createState() => _OnDemandFormWidgetState();
}

class _OnDemandFormWidgetState extends State<OnDemandFormWidget> {
  final _formKey = GlobalKey<FormState>();
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;

  final TextEditingController _pickupAddressController = TextEditingController();
  final TextEditingController _deliveryAddressController = TextEditingController();
  final TextEditingController _packageWeightController = TextEditingController();
  final TextEditingController _packageVolumeController = TextEditingController();
  final TextEditingController _estimatedMileageController = TextEditingController();
  final TextEditingController _packageDescController = TextEditingController();

  DateTime? _selectedPickupDateTime;
  DateTime? _selectedPreferredDeliveryDate;
  String? _selectedPreferredDeliveryWindow;

  double _estimatedCost = 0.00;
  bool _isSubmitting = false;
  String _costExplanation = ''; // To show why a cost is applied or not

  // Define your package restriction limits here
  static const double maxStandardWeightLbs = 150.0;
  static const double maxStandardVolumeCuFt = 9.0; // Corresponds to approx 108 inches (9ft) length for basic shapes

  @override
  void initState() {
    super.initState();
    _packageWeightController.addListener(_calculateCost);
    _packageVolumeController.addListener(_calculateCost);
    _estimatedMileageController.addListener(_calculateCost);
    _calculateCost(); // Initial calculation
  }

  @override
  void dispose() {
    _pickupAddressController.dispose();
    _deliveryAddressController.dispose();
    _packageWeightController.dispose();
    _packageVolumeController.dispose();
    _estimatedMileageController.dispose();
    _packageDescController.dispose();
    super.dispose();
  }

  // Update cost when appSettings or currentUserPlan changes (from parent)
  @override
  void didUpdateWidget(covariant OnDemandFormWidget oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.appSettings != oldWidget.appSettings || widget.currentUserPlan != oldWidget.currentUserPlan) {
      _calculateCost();
    }
  }

  void _calculateCost() {
    setState(() {
      final weight = double.tryParse(_packageWeightController.text) ?? 0.0;
      final volume = double.tryParse(_packageVolumeController.text) ?? 0.0;
      final mileage = double.tryParse(_estimatedMileageController.text) ?? 0.0;

      // Calculate base cost based on weight/volume/mileage
      final costByWeightAndVolume = (weight * widget.appSettings.onDemandRatePerLb) +
          (volume * widget.appSettings.onDemandRatePerCubicFoot);
      final costByMileage = mileage * widget.appSettings.onDemandRatePerMile;
      double calculatedBaseCost = (costByWeightAndVolume > costByMileage ? costByMileage : costByWeightAndVolume); // Corrected logic

      // Apply surge multiplier
      calculatedBaseCost *= widget.appSettings.surgeMultiplier;

      // Now apply the specific business logic for subscribers
      if (widget.currentUserPlan == PricingPlan.subscription) {
        // Check if item is oversized for subscribers
        bool isOversized = (weight > maxStandardWeightLbs) || (volume > maxStandardVolumeCuFt);

        if (isOversized) {
          _estimatedCost = calculatedBaseCost;
          _costExplanation = 'This On-Demand service is chargeable as the item is oversized.';
        } else {
          _estimatedCost = 0.00; // Covered by subscription
          _costExplanation = 'Covered by your monthly subscription (standard size item).';
        }
      } else {
        // Pay-as-You-Go customer always pays for On-Demand
        _estimatedCost = calculatedBaseCost;
        _costExplanation = 'Pay-as-you-go price for On-Demand service.';
      }

      // Add base delivery fee if it applies to Pay-as-You-Go On-Demand
      _estimatedCost += widget.appSettings.baseDeliveryFee;

      // Ensure cost doesn't go below 0 (though unlikely with positive rates)
      _estimatedCost = _estimatedCost.clamp(0.00, double.infinity);
    });
  }

  Future<void> _selectPickupDateTime(BuildContext context) async {
    final DateTime? pickedDate = await showDatePicker(
      context: context,
      initialDate: _selectedPickupDateTime ?? DateTime.now().add(const Duration(minutes: 15)),
      firstDate: DateTime.now().add(const Duration(minutes: 15)),
      lastDate: DateTime(2101),
    );
    if (pickedDate != null) {
      final TimeOfDay? pickedTime = await showTimePicker(
        context: context,
        initialTime: TimeOfDay.fromDateTime(_selectedPickupDateTime ?? DateTime.now().add(const Duration(minutes: 15))),
      );
      if (pickedTime != null) {
        setState(() {
          _selectedPickupDateTime = DateTime(
            pickedDate.year,
            pickedDate.month,
            pickedDate.day,
            pickedTime.hour,
            pickedTime.minute,
          );
        });
      }
    }
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

    if (_selectedPickupDateTime == null || _selectedPreferredDeliveryDate == null || _selectedPreferredDeliveryWindow == null) {
      widget.onStatusUpdate('Please select all required date and time options.');
      return;
    }

    setState(() {
      _isSubmitting = true;
      widget.onStatusUpdate('Submitting order...');
    });

    try {
      await _firestore.collection('orders').add({
        'userId': widget.currentUserId,
        'serviceType': 'on_demand',
        'pricingPlanUsed': widget.currentUserPlan.name,
        'pickupAddress': _pickupAddressController.text,
        'finalDeliveryAddress': _deliveryAddressController.text,
        'packageWeight': double.tryParse(_packageWeightController.text) ?? 0.0,
        'packageVolume': double.tryParse(_packageVolumeController.text) ?? 0.0,
        'estimatedMileage': double.tryParse(_estimatedMileageController.text) ?? 0.0,
        'packageDesc': _packageDescController.text,
        'pickupTime': Timestamp.fromDate(_selectedPickupDateTime!),
        'preferredDeliveryDate': _selectedPreferredDeliveryDate!.toIso8601String().split('T')[0],
        'preferredDeliveryWindow': _selectedPreferredDeliveryWindow,
        'status': 'pending',
        'deliveryFee': _estimatedCost,
        'surgeApplied': widget.appSettings.surgeMultiplier > 1,
        'createdAt': FieldValue.serverTimestamp(),
      });
      widget.onStatusUpdate('On-Demand order placed successfully! Estimated cost: \$${_estimatedCost.toStringAsFixed(2)}');
      _formKey.currentState?.reset();
      _pickupAddressController.clear();
      _deliveryAddressController.clear();
      _packageWeightController.clear();
      _packageVolumeController.clear();
      _estimatedMileageController.clear();
      _packageDescController.clear();
      setState(() {
        _selectedPickupDateTime = null;
        _selectedPreferredDeliveryDate = null;
        _selectedPreferredDeliveryWindow = null;
        _estimatedCost = 0.00;
        _costExplanation = '';
      });
      _calculateCost(); // Recalculate after clearing form
    } catch (error) {
      print("Error placing on-demand order: $error");
      widget.onStatusUpdate('Error: Failed to place order. ${error.toString()}');
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
              // *** INCREASED FONT SIZE AND WEIGHT FOR "On-Demand Details" HEADING ***
              Padding(
                padding: const EdgeInsets.only(bottom: 16.0, top: 8.0), // Added top padding
                child: Text(
                  'On-Demand Details',
                  style: TextStyle(
                    fontSize: 20.0, // Increased from 18.0
                    fontWeight: FontWeight.bold, // Increased from w600
                    color: colorScheme.onBackground,
                  ),
                ),
              ),
              const Divider(height: 16, thickness: 1), // Adjusted height for spacing

              Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: TextFormField(
                  controller: _pickupAddressController,
                  decoration: _formInputDecoration(
                    'Pickup Address',
                    hintText: 'Your current location or desired pickup spot',
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter a pickup address';
                    }
                    return null;
                  },
                ),
              ),

              Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: TextFormField(
                  controller: _deliveryAddressController,
                  decoration: _formInputDecoration(
                    'Final Delivery Address',
                    hintText: 'Your home address',
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter a final delivery address';
                    }
                    return null;
                  },
                ),
              ),

              Row(
                children: [
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(right: 8.0, bottom: 16.0),
                      child: TextFormField(
                        controller: _packageWeightController,
                        decoration: _formInputDecoration(
                          'Package Weight (lbs)',
                          hintText: 'e.g., 5.5',
                        ),
                        keyboardType: TextInputType.number,
                        validator: (value) {
                          if (value == null || value.isEmpty) return 'Enter weight';
                          if (double.tryParse(value) == null) return 'Invalid number';
                          if (double.parse(value) <= 0) return 'Weight must be positive';
                          return null;
                        },
                      ),
                    ),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(left: 8.0, bottom: 16.0),
                      child: TextFormField(
                        controller: _packageVolumeController,
                        decoration: _formInputDecoration(
                          'Package Volume (cu ft)',
                          hintText: 'e.g., 1.2',
                        ),
                        keyboardType: TextInputType.number,
                        validator: (value) {
                          if (value == null || value.isEmpty) return 'Enter volume';
                          if (double.tryParse(value) == null) return 'Invalid number';
                          if (double.parse(value) <= 0) return 'Volume must be positive';
                          return null;
                        },
                      ),
                    ),
                  ),
                ],
              ),

              Padding(
                padding: const EdgeInsets.only(bottom: 8.0),
                child: TextFormField(
                  controller: _estimatedMileageController,
                  decoration: _formInputDecoration(
                    'Estimated Mileage (miles)',
                    hintText: 'e.g., 10.5',
                  ),
                  keyboardType: TextInputType.number,
                  validator: (value) {
                    if (value == null || value.isEmpty) return 'Enter mileage';
                    if (double.tryParse(value) == null) return 'Invalid number';
                    if (double.parse(value) <= 0) return 'Mileage must be positive';
                    return null;
                  },
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: Text(
                  'This is an estimate. Actual mileage will be confirmed for final pricing.',
                  style: TextStyle(fontSize: 10.0, color: colorScheme.outline),
                ),
              ),

              Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: TextFormField(
                  controller: _packageDescController,
                  decoration: _formInputDecoration(
                    'Package Description',
                    hintText: 'e.g., Small box, fragile documents, clothes',
                  ),
                  maxLines: 2,
                ),
              ),

              Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: InkWell(
                  onTap: _isSubmitting ? null : () => _selectPickupDateTime(context),
                  child: InputDecorator(
                    decoration: _formInputDecoration('Scheduled Pickup Time (Date & Time)'),
                    baseStyle: TextStyle(color: colorScheme.onSurface),
                    child: Text(
                      _selectedPickupDateTime == null
                          ? 'Select a date and time'
                          : '${_selectedPickupDateTime!.toLocal().toIso8601String().split('T')[0]} ${_selectedPickupDateTime!.toLocal().hour}:${_selectedPickupDateTime!.toLocal().minute.toString().padLeft(2, '0')}',
                      style: TextStyle(color: colorScheme.onSurface, fontSize: 16),
                    ),
                  ),
                ),
              ),

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
              Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: Row( // Wrap DropdownButtonFormField in a Row to give it explicit expansion
                  children: [
                    Expanded( // Makes DropdownButtonFormField take all available width
                      child: DropdownButtonFormField<String>(
                        isExpanded: true, // IMPORTANT: Allows the dropdown to take all available horizontal space.
                        value: _selectedPreferredDeliveryWindow,
                        decoration: _formInputDecoration('Preferred Delivery Window'),
                        hint: Text('Select a time window', style: TextStyle(color: colorScheme.outline)),
                        items: <String>['1:00 PM - 4:00 PM', '4:00 PM - 7:00 PM', '7:00 PM - 10:00 PM']
                            .map<DropdownMenuItem<String>>((String value) {
                          return DropdownMenuItem<String>(
                            value: value,
                            // Ensure long text within the item is truncated
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
                  ],
                ),
              ),

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
                      'Estimated On-Demand Cost:',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: colorScheme.onPrimaryContainer,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '\$${_estimatedCost.toStringAsFixed(2)}',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 30.0,
                        fontWeight: FontWeight.bold,
                        color: colorScheme.primary,
                      ),
                    ),
                    Text(
                      _costExplanation.isNotEmpty
                          ? _costExplanation
                          : 'Excludes potential surge pricing and tolls.', // Default message
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 10.0, color: colorScheme.onPrimaryContainer),
                    ),
                  ],
                ),
              ),

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
                      : const Text('Request On-Demand Service'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
