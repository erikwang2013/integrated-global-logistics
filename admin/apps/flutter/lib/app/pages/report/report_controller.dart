// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:get/get.dart';
import '../../services/api_service.dart';

class ReportController extends GetxController {
  final _api = ApiService();
  final isLoading = true.obs;
  final days = 30.obs;
  final errorMessage = RxnString();

  final trackingOverview = <String, dynamic>{}.obs;
  final trackingByDay = <Map<String, dynamic>>[].obs;
  final trackingByCarrier = <Map<String, dynamic>>[].obs;

  final orderOverview = <String, dynamic>{}.obs;
  final orderByDay = <Map<String, dynamic>>[].obs;
  final orderByChannel = <Map<String, dynamic>>[].obs;

  @override
  void onInit() {
    super.onInit();
    loadData();
  }

  Future<void> setDays(int d) async {
    if (days.value == d) return;
    days.value = d;
    await loadData();
  }

  Future<void> loadData() async {
    try {
      isLoading.value = true;
      errorMessage.value = null;
      final results = await Future.wait([
        _api.get('/admin/tracking/statistics', params: {'days': days.value}),
        _api.get('/admin/order/statistics', params: {'days': days.value}),
      ]);
      final tData = results[0]['data'];
      final oData = results[1]['data'];
      trackingOverview.value = Map<String, dynamic>.from(tData['overview'] ?? {});
      trackingByDay.value = List<Map<String, dynamic>>.from(tData['by_day'] ?? []);
      trackingByCarrier.value = List<Map<String, dynamic>>.from(tData['by_carrier'] ?? []);
      orderOverview.value = Map<String, dynamic>.from(oData['overview'] ?? {});
      orderByDay.value = List<Map<String, dynamic>>.from(oData['by_day'] ?? []);
      orderByChannel.value = List<Map<String, dynamic>>.from(oData['by_channel'] ?? []);
    } catch (e) {
      errorMessage.value = e.toString();
    } finally {
      isLoading.value = false;
    }
  }
}
