import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';

Future<String> resolveDeviceName() async {
  if (kIsWeb) return 'Web browser';
  final plugin = DeviceInfoPlugin();
  if (Platform.isAndroid) {
    final info = await plugin.androidInfo;
    return '${info.manufacturer} ${info.model}'.trim();
  }
  if (Platform.isIOS) {
    final info = await plugin.iosInfo;
    return info.utsname.machine;
  }
  return Platform.operatingSystem;
}
