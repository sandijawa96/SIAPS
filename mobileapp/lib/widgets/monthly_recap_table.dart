import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../models/monthly_recap_data.dart';

class MonthlyRecapTable extends StatelessWidget {
  final MonthlyRecapData data;
  final String title;

  const MonthlyRecapTable({
    Key? key,
    required this.data,
    this.title = 'Rekapitulasi Bulan Berjalan',
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(
          color: Colors.grey.withOpacity(0.3),
          width: 1,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withOpacity(0.2),
            spreadRadius: 2,
            blurRadius: 15,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header
          _TableHeader(title: title),

          const SizedBox(height: 15),

          // Table Content
          _TableContent(data: data),
        ],
      ),
    );
  }
}

class _TableHeader extends StatelessWidget {
  final String title;

  const _TableHeader({Key? key, required this.title}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(
          Icons.calendar_month,
          color: Color(AppColors.primaryColorValue),
          size: 24,
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            title,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: Colors.grey[800],
            ),
            overflow: TextOverflow.ellipsis,
            maxLines: 1,
          ),
        ),
      ],
    );
  }
}

class _TableContent extends StatelessWidget {
  final MonthlyRecapData data;

  const _TableContent({Key? key, required this.data}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Row 1: Masuk, Izin, Alpa, Cuti
        Row(
          children: [
            _RecapItem(
                label: 'Masuk',
                value: data.masuk,
                unit: 'Hari',
                color: Colors.green),
            _RecapItem(
                label: 'Izin',
                value: data.izin,
                unit: 'Hari',
                color: Colors.blue),
            _RecapItem(
                label: 'Alpa',
                value: data.alpa,
                unit: 'Hari',
                color: Colors.red),
            _RecapItem(
                label: 'Cuti',
                value: data.cuti,
                unit: 'Hari',
                color: Colors.purple),
          ],
        ),

        const SizedBox(height: 15),

        // Row 2: Terlambat, Lupa Pulang, Alpha Menit, Total TK
        Row(
          children: [
            _RecapItem(
                label: 'Terlambat',
                value: data.terlambatHari,
                unit: 'Hari',
                color: Colors.orange),
            _RecapItem(
                label: 'Lupa Pulang (TAP)',
                value: data.tapHari,
                unit: 'Hari',
                color: Colors.teal),
            _RecapItem(
                label: 'Alpha',
                value: data.alpaMenit,
                unit: 'Menit',
                color: Colors.red),
            _RecapItem(
                label: 'Total TK',
                value: data.totalTK,
                unit: 'Menit',
                color: Color(AppColors.primaryColorValue)),
          ],
        ),
      ],
    );
  }
}

class _RecapItem extends StatelessWidget {
  final String label;
  final int value;
  final String unit;
  final Color color;

  const _RecapItem({
    Key? key,
    required this.label,
    required this.value,
    required this.unit,
    required this.color,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 2),
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 6),
        decoration: BoxDecoration(
          color: color.withOpacity(0.1),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
            color: color.withOpacity(0.3),
            width: 1,
          ),
        ),
        child: Column(
          children: [
            // Label di atas
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                color: Colors.grey[600],
                fontWeight: FontWeight.w500,
              ),
              textAlign: TextAlign.center,
              overflow: TextOverflow.ellipsis,
              maxLines: 1,
            ),
            const SizedBox(height: 4),
            // Angka di tengah
            Text(
              value.toString(),
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: color,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 2),
            // Unit di bawah
            Text(
              unit,
              style: TextStyle(
                fontSize: 10,
                color: Colors.grey[500],
                fontWeight: FontWeight.w400,
              ),
              textAlign: TextAlign.center,
              overflow: TextOverflow.ellipsis,
              maxLines: 1,
            ),
          ],
        ),
      ),
    );
  }
}
