import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'providers.dart';

/// True when a physical Android phone is pointed at the emulator-only host 10.0.2.2.
final showPhysicalDeviceApiHintProvider = FutureProvider<bool>((ref) async {
  if (kIsWeb || !Platform.isAndroid) return false;
  final config = ref.watch(appConfigProvider);
  if (config.isProduction) return false;
  if (!config.apiBaseUrl.contains('10.0.2.2')) return false;
  final info = await DeviceInfoPlugin().androidInfo;
  return info.isPhysicalDevice;
});
