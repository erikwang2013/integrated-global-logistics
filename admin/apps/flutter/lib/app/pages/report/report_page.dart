// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import 'report_controller.dart';
import '../../i18n/translations.dart';

const _channelColors = {
  'stripe': Color(0xFF1677FF),
  'paypal': Color(0xFF52C41A),
  'crypto': Color(0xFFFA8C16),
  'manual': Color(0xFF722ED1),
};
const _palette = [
  Color(0xFF1677FF),
  Color(0xFF52C41A),
  Color(0xFFFA8C16),
  Color(0xFF722ED1),
  Color(0xFF13C2C2),
  Color(0xFFEB2F96),
];

class ReportPage extends GetView<ReportController> {
  const ReportPage({super.key});

  @override
  Widget build(BuildContext context) {
    Get.put(ReportController());
    return Obx(() {
      if (controller.isLoading.value && controller.trackingByDay.isEmpty) {
        return const Center(child: CircularProgressIndicator());
      }

      return SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Flexible(
                  child: Text(t('report_title'),
                      style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold), overflow: TextOverflow.ellipsis),
                ),
                const Spacer(),
                SegmentedButton<int>(
                  segments: const [
                    ButtonSegment(value: 7, label: Text('7')),
                    ButtonSegment(value: 30, label: Text('30')),
                    ButtonSegment(value: 90, label: Text('90')),
                  ],
                  selected: {controller.days.value},
                  onSelectionChanged: (s) => controller.setDays(s.first),
                ),
                const SizedBox(width: 8),
                IconButton(icon: const Icon(Icons.refresh), tooltip: t('refresh'), onPressed: () => controller.loadData()),
              ],
            ),
            if (controller.errorMessage.value != null) ...[
              const SizedBox(height: 12),
              _buildErrorBanner(),
            ],
            const SizedBox(height: 24),
            _buildTrackingCard(),
            const SizedBox(height: 24),
            _buildOrderCard(),
          ],
        ),
      );
    });
  }

  Widget _buildErrorBanner() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.red[50],
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.red[200]!),
      ),
      child: Text(controller.errorMessage.value!, style: TextStyle(color: Colors.red[700], fontSize: 13)),
    );
  }

  // ─── 轨迹查询报表 ─────────────────────────────────────────────

  Widget _buildTrackingCard() {
    final ov = controller.trackingOverview;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('轨迹查询报表', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            _buildMetricGrid([
              _metric(t('report_total_queries'), '${ov['total_queries'] ?? 0}', Icons.search, const Color(0xFF1677FF)),
              _metric(t('report_success_count'), '${ov['success_count'] ?? 0}', Icons.check_circle, const Color(0xFF52C41A)),
              _metric(t('report_success_rate'), '${ov['success_rate'] ?? 0}%', Icons.trending_up, const Color(0xFFFA8C16)),
              _metric(t('report_avg_cost'), '${ov['avg_cost_ms'] ?? 0} ms', Icons.timer, const Color(0xFF722ED1)),
            ]),
            const SizedBox(height: 24),
            const Text('按日趋势', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
            const SizedBox(height: 12),
            _buildQueriesLineChart(),
            const SizedBox(height: 24),
            const Text('按承运商', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
            const SizedBox(height: 12),
            _buildCarrierBarChart(),
          ],
        ),
      ),
    );
  }

  Widget _buildQueriesLineChart() {
    final byDay = controller.trackingByDay;
    if (byDay.isEmpty) return _buildEmptyChart();
    final spots = byDay.asMap().entries
        .map((e) => FlSpot(e.key.toDouble(), ((e.value['queries'] ?? 0) as num).toDouble()))
        .toList();
    return _buildLineChart(spots, const Color(0xFF1677FF));
  }

  Widget _buildCarrierBarChart() {
    final byCarrier = controller.trackingByCarrier;
    if (byCarrier.isEmpty) return _buildEmptyChart();
    final maxQ = byCarrier.fold<double>(
      0,
      (m, e) => math.max(m, ((e['queries'] ?? 0) as num).toDouble()),
    );
    return SizedBox(
      height: 250,
      child: BarChart(
        BarChartData(
          minY: 0,
          maxY: math.max(maxQ * 1.2, 1),
          gridData: const FlGridData(show: true, drawVerticalLine: false),
          titlesData: FlTitlesData(
            bottomTitles: AxisTitles(
              sideTitles: SideTitles(
                showTitles: true,
                reservedSize: 40,
                getTitlesWidget: (v, _) {
                  final i = v.toInt();
                  if (i < 0 || i >= byCarrier.length) return const SizedBox.shrink();
                  return RotatedBox(
                    quarterTurns: 3,
                    child: Text('${byCarrier[i]['carrier_code'] ?? ''}', style: const TextStyle(fontSize: 10)),
                  );
                },
              ),
            ),
            leftTitles: AxisTitles(
              sideTitles: SideTitles(showTitles: true, reservedSize: 40, getTitlesWidget: (v, _) => Text('${v.toInt()}')),
            ),
            topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          ),
          borderData: FlBorderData(show: false),
          barGroups: byCarrier.asMap().entries.map((e) {
            return BarChartGroupData(
              x: e.key,
              barRods: [BarChartRodData(toY: ((e.value['queries'] ?? 0) as num).toDouble(), color: const Color(0xFF1677FF), width: 18)],
            );
          }).toList(),
        ),
      ),
    );
  }

  // ─── 订单报表 ─────────────────────────────────────────────────

  Widget _buildOrderCard() {
    final ov = controller.orderOverview;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('订单报表', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            _buildMetricGrid([
              _metric(t('report_total_orders'), '${ov['total_orders'] ?? 0}', Icons.receipt_long, const Color(0xFF1677FF)),
              _metric(t('report_paid_count'), '${ov['paid_count'] ?? 0}', Icons.check_circle, const Color(0xFF52C41A)),
              _metric(t('report_paid_amount'), '¥${ov['paid_amount'] ?? 0}', Icons.payments, const Color(0xFFFA8C16)),
              _metric(t('report_paid_rate'), '${ov['paid_rate'] ?? 0}%', Icons.trending_up, const Color(0xFF722ED1)),
            ]),
            const SizedBox(height: 24),
            const Text('按日趋势', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
            const SizedBox(height: 12),
            _buildOrdersLineChart(),
            const SizedBox(height: 24),
            const Text('渠道分布', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
            const SizedBox(height: 12),
            _buildChannelPieChart(),
          ],
        ),
      ),
    );
  }

  Widget _buildOrdersLineChart() {
    final byDay = controller.orderByDay;
    if (byDay.isEmpty) return _buildEmptyChart();
    final spots = byDay.asMap().entries
        .map((e) => FlSpot(e.key.toDouble(), ((e.value['orders'] ?? 0) as num).toDouble()))
        .toList();
    return _buildLineChart(spots, const Color(0xFF52C41A));
  }

  Widget _buildChannelPieChart() {
    final byChannel = controller.orderByChannel;
    if (byChannel.isEmpty) return _buildEmptyChart();
    final sections = byChannel.asMap().entries.map((e) {
      final name = e.value['channel']?.toString() ?? '';
      return PieChartSectionData(
        color: _channelColors[name] ?? _palette[e.key % _palette.length],
        value: ((e.value['orders'] ?? 0) as num).toDouble(),
        title: '',
        radius: 30,
      );
    }).toList();
    return Column(
      children: [
        SizedBox(
          height: 220,
          child: PieChart(PieChartData(sections: sections, centerSpaceRadius: 40, sectionsSpace: 2)),
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 16,
          runSpacing: 8,
          alignment: WrapAlignment.center,
          children: [
            for (final e in byChannel.asMap().entries)
              _buildLegend(
                _channelColors[e.value['channel']] ?? _palette[e.key % _palette.length],
                e.value['channel']?.toString() ?? '',
              ),
          ],
        ),
      ],
    );
  }

  // ─── 通用组件 ─────────────────────────────────────────────────

  Widget _buildMetricGrid(List<Map<String, dynamic>> items) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final crossAxisCount = constraints.maxWidth > 700 ? 4 : 2;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: crossAxisCount,
            mainAxisExtent: 100,
            crossAxisSpacing: 16,
            mainAxisSpacing: 16,
          ),
          itemCount: items.length,
          itemBuilder: (context, index) {
            final item = items[index];
            return Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Icon(item['icon'] as IconData, color: item['color'] as Color, size: 18),
                      const Spacer(),
                    ]),
                    const Spacer(),
                    Text(item['label'] as String, style: TextStyle(fontSize: 12, color: Colors.grey[600])),
                    const SizedBox(height: 4),
                    Text(item['value'] as String, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Map<String, dynamic> _metric(String label, String value, IconData icon, Color color) =>
      {'label': label, 'value': value, 'icon': icon, 'color': color};

  Widget _buildLineChart(List<FlSpot> spots, Color color) {
    return SizedBox(
      height: 250,
      child: LineChart(
        LineChartData(
          minY: 0,
          gridData: const FlGridData(show: true, drawVerticalLine: false),
          titlesData: FlTitlesData(
            bottomTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            leftTitles: AxisTitles(
              sideTitles: SideTitles(showTitles: true, reservedSize: 40, getTitlesWidget: (v, _) => Text('${v.toInt()}')),
            ),
            topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          ),
          borderData: FlBorderData(show: false),
          lineBarsData: [
            LineChartBarData(
              spots: spots,
              color: color,
              barWidth: 2,
              dotData: const FlDotData(show: false),
              belowBarData: BarAreaData(show: true, color: color.withValues(alpha: 0.1)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyChart() {
    return SizedBox(
      height: 220,
      child: Center(child: Text(t('report_empty'), style: TextStyle(color: Colors.grey[500]))),
    );
  }

  Widget _buildLegend(Color color, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 12, height: 12, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(2))),
        const SizedBox(width: 4),
        Text(label, style: const TextStyle(fontSize: 12)),
      ],
    );
  }
}
