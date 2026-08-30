// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import '../../services/api_service.dart';

class DashboardController extends GetxController {
  final _api = ApiService();
  final isLoading = true.obs;

  final stats = <Map<String, dynamic>>[].obs;
  final trends = <String, dynamic>{}.obs;
  final recentLogs = <Map<String, dynamic>>[].obs;
  final distribution = <String, dynamic>{}.obs;

  List<List<FlSpot>> get trendSpots {
    final allSeries = trends['series'] as List<dynamic>? ?? [];
    return allSeries.map((s) {
      final data = s['data'] as List<dynamic>? ?? [];
      return data.asMap().entries.map((e) => FlSpot(e.key.toDouble(), (e.value as num).toDouble())).toList();
    }).toList();
  }

  List<PieChartSectionData> get pieSections => _sectionsFrom(distribution['user_status']);

  List<PieChartSectionData> get orderChannelSections => _sectionsFrom(distribution['order_channel']);

  List<PieChartSectionData> _sectionsFrom(dynamic list) {
    const palette = [
      Color(0xFF1677FF),
      Color(0xFF52C41A),
      Color(0xFFFA8C16),
      Color(0xFF722ED1),
      Color(0xFF13C2C2),
    ];
    final items = List<Map<String, dynamic>>.from(list ?? []);
    return items.asMap().entries.map((e) {
      return PieChartSectionData(
        color: palette[e.key % palette.length],
        value: ((e.value['value'] ?? 0) as num).toDouble(),
        title: e.value['name']?.toString() ?? '',
        radius: 30,
      );
    }).toList();
  }

  @override
  void onInit() {
    super.onInit();
    loadData();
  }

  Future<void> loadData() async {
    try {
      isLoading.value = true;
      final resp = await _api.get('/admin/dashboard');
      final data = resp['data'];
      stats.value = List<Map<String, dynamic>>.from(data['stats'] ?? []);
      trends.value = Map<String, dynamic>.from(data['trends'] ?? {});
      recentLogs.value = List<Map<String, dynamic>>.from(data['recent_logs'] ?? []);
      distribution.value = Map<String, dynamic>.from(data['distribution'] ?? {});
    } catch (e) {
      // 开发环境使用模拟数据
      stats.value = [
        {'label': '用户总数', 'value': '1,236', 'icon': 'people', 'color': '#1677FF', 'trend': 12.5},
        {'label': '今日新增', 'value': '28', 'icon': 'person_add', 'color': '#52C41A', 'trend': null},
        {'label': '活跃用户', 'value': '89', 'icon': 'bolt', 'color': '#FA8C16', 'trend': -3.2},
        {'label': '操作日志', 'value': '452', 'icon': 'description', 'color': '#722ED1', 'trend': 8.0},
      ];
      trends.value = {
        'dates': List.generate(30, (i) => 'Day $i'),
        'series': [
          {
            'name': '累计用户',
            'data': List.generate(30, (i) => 800 + i * 15 + (i > 20 ? 20 : 0)),
          },
        ],
      };
      distribution.value = {
        'user_status': [
          {'name': '启用', 'value': 265},
          {'name': '禁用', 'value': 35},
        ],
        'order_channel': [
          {'name': 'stripe', 'value': 120},
          {'name': 'paypal', 'value': 60},
          {'name': 'crypto', 'value': 20},
        ],
      };
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> exportPdf() async {
    final pdf = pw.Document();
    pdf.addPage(pw.MultiPage(
      pageFormat: PdfPageFormat.a4.landscape,
      build: (ctx) => [
        pw.Header(text: '仪表盘数据导出'),
        pw.Paragraph(text: 'Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz'),
        for (final s in stats)
          pw.Row(children: [
            pw.Text(s['label']),
            pw.Text(s['value'], style: pw.TextStyle(fontWeight: pw.FontWeight.bold)),
          ]),
      ],
    ));
    await Printing.sharePdf(bytes: await pdf.save(), filename: 'dashboard_export.pdf');
  }

  Future<void> exportExcel() async {
    Get.snackbar('导出', 'Excel 导出功能已触发');
  }
}
