package cloud.wcodex.zentra.supervision;

import android.Manifest;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.content.pm.ServiceInfo;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.os.Build;
import android.os.IBinder;
import android.os.Looper;
import android.os.PowerManager;
import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;
import androidx.core.content.ContextCompat;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Locale;
import java.util.UUID;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class ShiftTrackingService extends Service implements LocationListener {
    private static final String CHANNEL = "zentra_shift_gps";
    private static final int NOTICE_ID = 2201;
    private LocationManager locationManager;
    private Location lastLocation;
    private ScheduledExecutorService ticker;

    @Override
    public void onCreate() {
        super.onCreate();
        ensureChannel();
        Notification notice = buildNotice();
        try {
            if (Build.VERSION.SDK_INT >= 34) {
                startForeground(NOTICE_ID, notice, ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION);
            } else {
                startForeground(NOTICE_ID, notice);
            }
        } catch (Exception e) {
            stopSelf();
            return;
        }
        startLocations();
        ticker = Executors.newSingleThreadScheduledExecutor();
        ticker.scheduleAtFixedRate(this::pingOnce, 2, 15, TimeUnit.SECONDS);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        return START_STICKY;
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    @Override
    public void onDestroy() {
        if (ticker != null) {
            ticker.shutdownNow();
            ticker = null;
        }
        if (locationManager != null) {
            try {
                locationManager.removeUpdates(this);
            } catch (Exception ignored) {
            }
        }
        super.onDestroy();
    }

    @Override
    public void onLocationChanged(Location location) {
        if (location != null) {
            lastLocation = location;
        }
    }

    private void startLocations() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION)
            != PackageManager.PERMISSION_GRANTED) {
            return;
        }
        locationManager = (LocationManager) getSystemService(LOCATION_SERVICE);
        if (locationManager == null) {
            return;
        }
        try {
            lastLocation = locationManager.getLastKnownLocation(LocationManager.GPS_PROVIDER);
            if (lastLocation == null) {
                lastLocation = locationManager.getLastKnownLocation(LocationManager.NETWORK_PROVIDER);
            }
            if (locationManager.isProviderEnabled(LocationManager.GPS_PROVIDER)) {
                locationManager.requestLocationUpdates(
                    LocationManager.GPS_PROVIDER, 15000, 5, this, Looper.getMainLooper()
                );
            }
            if (locationManager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) {
                locationManager.requestLocationUpdates(
                    LocationManager.NETWORK_PROVIDER, 15000, 8, this, Looper.getMainLooper()
                );
            }
        } catch (SecurityException ignored) {
        }
    }

    private void pingOnce() {
        String token = ShiftTrackingStore.token(this);
        String apiBase = ShiftTrackingStore.apiBase(this);
        Location loc = lastLocation;
        if (token.isEmpty() || apiBase.isEmpty() || loc == null) {
            return;
        }
        boolean screenOn = false;
        PowerManager power = (PowerManager) getSystemService(POWER_SERVICE);
        if (power != null) {
            screenOn = power.isInteractive();
        }
        String body = String.format(
            Locale.US,
            "{\"latitude\":%.7f,\"longitude\":%.7f,\"accuracy\":%.1f,\"client_event_id\":\"%s\",\"screen_on\":%s,\"source\":\"apk\"}",
            loc.getLatitude(),
            loc.getLongitude(),
            loc.hasAccuracy() ? loc.getAccuracy() : 0,
            UUID.randomUUID().toString(),
            screenOn ? "true" : "false"
        );
        HttpURLConnection conn = null;
        try {
            URL url = new URL(apiBase + "/supervision/shifts/ping");
            conn = (HttpURLConnection) url.openConnection();
            conn.setConnectTimeout(15000);
            conn.setReadTimeout(15000);
            conn.setRequestMethod("POST");
            conn.setDoOutput(true);
            conn.setRequestProperty("Authorization", "Bearer " + token);
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("Content-Type", "application/json");
            byte[] bytes = body.getBytes(StandardCharsets.UTF_8);
            conn.setFixedLengthStreamingMode(bytes.length);
            OutputStream out = conn.getOutputStream();
            out.write(bytes);
            out.close();
            int code = conn.getResponseCode();
            if (code == 401 || code == 422) {
                stopSelf();
            }
        } catch (Exception ignored) {
        } finally {
            if (conn != null) {
                conn.disconnect();
            }
        }
    }

    private void ensureChannel() {
        if (Build.VERSION.SDK_INT < 26) {
            return;
        }
        NotificationChannel channel = new NotificationChannel(
            CHANNEL,
            getString(R.string.shift_tracking_channel),
            NotificationManager.IMPORTANCE_LOW
        );
        channel.setDescription(getString(R.string.shift_tracking_text));
        NotificationManager manager = getSystemService(NotificationManager.class);
        if (manager != null) {
            manager.createNotificationChannel(channel);
        }
    }

    private Notification buildNotice() {
        Intent launch = new Intent(this, MainActivity.class);
        launch.setFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP);
        PendingIntent pending = PendingIntent.getActivity(
            this,
            0,
            launch,
            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );
        return new NotificationCompat.Builder(this, CHANNEL)
            .setContentTitle(getString(R.string.shift_tracking_title))
            .setContentText(getString(R.string.shift_tracking_text))
            .setSmallIcon(R.mipmap.ic_launcher)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setContentIntent(pending)
            .setForegroundServiceBehavior(NotificationCompat.FOREGROUND_SERVICE_IMMEDIATE)
            .build();
    }
}
