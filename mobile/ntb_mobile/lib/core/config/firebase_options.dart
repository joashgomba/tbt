import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';

/// Replace this file by running `flutterfire configure` in `mobile/ntb_mobile`.
/// Until then, push is inactive unless you set real Firebase options here.
class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    if (kIsWeb) {
      throw UnsupportedError('Web push is not configured.');
    }
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      default:
        throw UnsupportedError('Push is only configured for iOS and Android.');
    }
  }

  /// Placeholder — run `flutterfire configure` to generate production values.
  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'REPLACE_ME',
    appId: '1:000000000000:android:0000000000000000000000',
    messagingSenderId: '000000000000',
    projectId: 'ntb-djibouti-placeholder',
    storageBucket: 'ntb-djibouti-placeholder.appspot.com',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'REPLACE_ME',
    appId: '1:000000000000:ios:0000000000000000000000',
    messagingSenderId: '000000000000',
    projectId: 'ntb-djibouti-placeholder',
    storageBucket: 'ntb-djibouti-placeholder.appspot.com',
    iosBundleId: 'dj.ntb.portal.ntbMobile',
  );
}
