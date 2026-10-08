import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:isu_supply_inspector/main.dart';
import 'package:isu_supply_inspector/services/server_discovery.dart';

void main() {
  testWidgets('App boots into server discovery when logged out and no server saved',
      (WidgetTester tester) async {
    SharedPreferences.setMockInitialValues({});
    // No mDNS sockets in tests — the scan finds nothing, so the screen lands
    // on the not-found state (which is static and settles).
    ServerDiscovery.scannerOverride = () async => [];
    addTearDown(() => ServerDiscovery.scannerOverride = null);

    // onboardingPending: false so the app goes straight to the login gate,
    // which skips straight to the discovery screen (no saved server).
    await tester.pumpWidget(const MyApp(onboardingPending: false));
    await tester.pumpAndSettle();

    expect(find.text('Connect to Server'), findsOneWidget);
    expect(find.text('Server not found'), findsOneWidget);
    expect(find.text('Scan Again'), findsOneWidget);
    // Manual entry stays available as the fallback, not the primary path.
    expect(find.text('Enter Server Manually'), findsOneWidget);
  });
}