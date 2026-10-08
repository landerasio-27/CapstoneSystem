import 'package:flutter_test/flutter_test.dart';

import 'package:isu_supply_desktop/main.dart';

void main() {
  testWidgets('App builds without throwing', (WidgetTester tester) async {
    await tester.pumpWidget(const IsuSupplyDesktopApp());
    expect(find.byType(IsuSupplyDesktopApp), findsOneWidget);
  });
}
