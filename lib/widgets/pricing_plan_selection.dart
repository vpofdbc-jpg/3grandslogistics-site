// lib/widgets/pricing_plan_selection.dart

import 'package:flutter/material.dart';

// Enum to define the two pricing plan types.
enum PricingPlan { payAsYouGo, subscription }

class PricingPlanSelection extends StatefulWidget {
  final PricingPlan initialPlan;
  final ValueChanged<PricingPlan> onPlanChanged;

  const PricingPlanSelection({
    super.key,
    required this.initialPlan,
    required this.onPlanChanged,
  });

  @override
  State<PricingPlanSelection> createState() => _PricingPlanSelectionState();
}

class _PricingPlanSelectionState extends State<PricingPlanSelection> {
  late PricingPlan _selectedPlan;

  @override
  void initState() {
    super.initState();
    _selectedPlan = widget.initialPlan;
  }

  // Ensure the widget updates if the initialPlan changes from the parent
  @override
  void didUpdateWidget(covariant PricingPlanSelection oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.initialPlan != oldWidget.initialPlan) {
      setState(() {
        _selectedPlan = widget.initialPlan;
      });
    }
  }

  // Helper Widget for the styled Pricing Plan Radio Card
  Widget _buildPricingPlanRadioCard({
    required PricingPlan value,
    required String title,
    required String description,
  }) {
    bool isSelected = (_selectedPlan == value);
    final colorScheme = Theme.of(context).colorScheme;

    return InkWell(
      onTap: () {
        setState(() {
          _selectedPlan = value;
          widget.onPlanChanged(value);
        });
        print('Selected plan changed to: $value');
      },
      child: Container(
        padding: const EdgeInsets.all(16.0),
        decoration: BoxDecoration(
          color: colorScheme.surface,
          borderRadius: BorderRadius.circular(12.0),
          boxShadow: [
            BoxShadow(
              color: colorScheme.shadow.withOpacity(0.1),
              blurRadius: 10,
              offset: const Offset(0, 5),
            ),
          ],
          border: Border.all(
            color: isSelected ? colorScheme.primary : Colors.transparent,
            width: 2.0,
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Radio<PricingPlan>(
              value: value,
              groupValue: _selectedPlan,
              onChanged: (PricingPlan? newValue) {
                if (newValue != null) {
                  setState(() {
                    _selectedPlan = newValue;
                    widget.onPlanChanged(newValue);
                  });
                   print('Selected plan changed to: $newValue (from radio)');
                }
              },
              activeColor: colorScheme.primary,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    // *** INCREASED FONT SIZE AND WEIGHT FOR PLAN TITLES ***
                    style: TextStyle(
                      fontSize: 18.0, // Increased from default
                      fontWeight: FontWeight.w600, // Increased from default
                      color: colorScheme.onSurface,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    description,
                    style: TextStyle(
                      fontSize: 12.0,
                      color: colorScheme.onSurfaceVariant,
                      height: 1.4,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 16.0), // Padding below the heading
          child: Text(
            'Your Delivery Options',
            // *** INCREASED FONT SIZE AND WEIGHT FOR MAIN HEADING ***
            style: TextStyle(
              fontSize: 22.0, // Increased from 18.0
              fontWeight: FontWeight.bold, // Increased from w600
              color: colorScheme.onBackground,
            ),
          ),
        ),
        _buildPricingPlanRadioCard(
          value: PricingPlan.payAsYouGo,
          title: 'On-Demand Delivery (Pay-as-You-Go)',
          description: 'Standard pricing for immediate and flexible delivery needs, billed per service.',
        ),
        const SizedBox(height: 16),
        _buildPricingPlanRadioCard(
          value: PricingPlan.subscription,
          title: 'VWC Subscription Plan',
          description: 'Monthly subscription for Vendor-to-Warehouse delivery, with special rates for oversized On-Demand items.',
        ),
      ],
    );
  }
}
