package edu.isu.cauayan.isu_supply_inspector

import android.content.Context
import android.net.wifi.WifiManager
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {

    private var multicastLock: WifiManager.MulticastLock? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        // Holds Android's Wi-Fi multicast lock while the app runs its mDNS
        // server discovery. Without it some devices drop multicast packets
        // (power-save Wi-Fi), making discovery unreliable. See
        // ServerDiscovery.acquireMulticastLock in server_discovery.dart.
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "isu_supply/multicast_lock")
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "acquire" -> {
                        try {
                            if (multicastLock == null) {
                                val wifi =
                                    applicationContext.getSystemService(Context.WIFI_SERVICE) as WifiManager
                                multicastLock =
                                    wifi.createMulticastLock("isu_supply_mdns").apply {
                                        setReferenceCounted(false)
                                    }
                            }
                            multicastLock?.acquire()
                            result.success(null)
                        } catch (e: Exception) {
                            result.error("MULTICAST_LOCK", e.message, null)
                        }
                    }
                    "release" -> {
                        multicastLock?.let { if (it.isHeld) it.release() }
                        result.success(null)
                    }
                    else -> result.notImplemented()
                }
            }
    }

    override fun onDestroy() {
        multicastLock?.let { if (it.isHeld) it.release() }
        multicastLock = null
        super.onDestroy()
    }
}