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
import android.os.HandlerThread;
import android.os.IBinder;
import android.os.Looper;
import android.os.PowerManager;
import android.os.SystemClock;
import com.google.android.gms.common.ConnectionResult;
import com.google.android.gms.common.GoogleApiAvailability;
import com.google.android.gms.location.FusedLocationProviderClient;
import com.google.android.gms.location.LocationCallback;
import com.google.android.gms.location.LocationRequest;
import com.google.android.gms.location.LocationResult;
import com.google.android.gms.location.LocationServices;
import com.google.android.gms.location.Priority;
import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;
import androidx.core.content.ContextCompat;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.Locale;
import java.util.TimeZone;
import java.util.UUID;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import org.json.JSONArray;
import org.json.JSONObject;

public class ShiftTrackingService extends Service implements LocationListener {
    public static boolean isRunning() {
        return running;
    }

    /** Epoch millis del último punto que entregó el GPS. 0 si todavía no hay. */
    public static long lastFixAt() {
        return lastFixAt;
    }

    private static final String CHANNEL = "zentra_shift_gps";
    private static final int NOTICE_ID = 2201;
    /** Si el último aviso tiene más de esto, se vuelve a pedir el GPS. No se deja de anotar. */
    private static final long NUDGE_AFTER_MS = 30_000L;
    /** Un punto más viejo que esto no se manda como si el supervisor estuviera ahí ahora. */
    private static final long LIVE_FIX_MS = 45_000L;
    private static final long SAMPLE_MS = 5_000L;
    private static final int BATCH = 300;

    private static volatile boolean running = false;
    private static volatile long lastFixAt = 0L;

    private LocationManager locationManager;
    private HandlerThread locationThread;
    private FusedLocationProviderClient fused;
    private LocationCallback fusedCallback;
    private PowerManager.WakeLock wakeLock;
    private volatile Location lastFix;
    private long lastSentFixTime = 0L;
    private long lastQueuedAt = 0L;
    private long lastNudgeAt = 0L;
    private ScheduledExecutorService ticker;
    private String cachedToken = "";
    private String cachedApi = "";
    private String cachedUser = "";

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
            running = false;
            stopSelf();
            return;
        }
        running = true;
        acquireWake();
        startLocations();
        ticker = Executors.newSingleThreadScheduledExecutor();
        ticker.scheduleWithFixedDelay(this::tick, 2, 5, TimeUnit.SECONDS);
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
        running = false;
        releaseWake();
        stopLocations();
        if (ticker != null) {
            ticker.shutdownNow();
            ticker = null;
        }
        final String token = cachedToken;
        final String api = cachedApi;
        final String user = cachedUser;
        if (!token.isEmpty() && !api.isEmpty()) {
            new Thread(() -> flush(api, token, user)).start();
        }
        super.onDestroy();
    }

    @Override
    public void onLocationChanged(Location location) {
        accept(location);
    }

    private void accept(Location location) {
        if (location == null) {
            return;
        }
        lastFix = location;
        lastFixAt = System.currentTimeMillis();
    }

    private void startLocations() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION)
            != PackageManager.PERMISSION_GRANTED
            && ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION)
            != PackageManager.PERMISSION_GRANTED) {
            return;
        }
        if (locationThread == null) {
            locationThread = new HandlerThread("zentra-gps");
            locationThread.start();
        }
        if (playServices()) {
            startFused();
            return;
        }
        startManager();
    }

    private boolean playServices() {
        try {
            return GoogleApiAvailability.getInstance().isGooglePlayServicesAvailable(this) == ConnectionResult.SUCCESS;
        } catch (Exception e) {
            return false;
        }
    }

    private void startFused() {
        try {
            fused = LocationServices.getFusedLocationProviderClient(this);
            LocationRequest request = new LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, 5_000L)
                .setMinUpdateIntervalMillis(4_000L)
                .setMaxUpdateDelayMillis(5_000L)
                .build();
            fusedCallback = new LocationCallback() {
                @Override
                public void onLocationResult(LocationResult result) {
                    if (result != null) {
                        accept(result.getLastLocation());
                    }
                }
            };
            fused.requestLocationUpdates(request, fusedCallback, locationThread.getLooper());
            fused.getCurrentLocation(Priority.PRIORITY_HIGH_ACCURACY, null).addOnSuccessListener(this::accept);
        } catch (Exception ignored) {
            startManager();
        }
    }

    private void startManager() {
        locationManager = (LocationManager) getSystemService(LOCATION_SERVICE);
        if (locationManager == null || locationThread == null) {
            return;
        }
        Looper looper = locationThread.getLooper();
        try {
            locationManager.removeUpdates(this);
        } catch (Exception ignored) {
        }
        try {
            if (locationManager.isProviderEnabled(LocationManager.GPS_PROVIDER)) {
                locationManager.requestLocationUpdates(LocationManager.GPS_PROVIDER, 5_000L, 0f, this, looper);
                accept(locationManager.getLastKnownLocation(LocationManager.GPS_PROVIDER));
            }
            if (locationManager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) {
                locationManager.requestLocationUpdates(LocationManager.NETWORK_PROVIDER, 5_000L, 0f, this, looper);
            }
        } catch (SecurityException ignored) {
        }
    }

    private void nudge() {
        long now = System.currentTimeMillis();
        if (now - lastNudgeAt < NUDGE_AFTER_MS) {
            return;
        }
        lastNudgeAt = now;
        if (fused != null) {
            try {
                fused.getCurrentLocation(Priority.PRIORITY_HIGH_ACCURACY, null).addOnSuccessListener(this::accept);
            } catch (SecurityException ignored) {
            }
            return;
        }
        startManager();
    }

    private void stopLocations() {
        if (fused != null && fusedCallback != null) {
            try {
                fused.removeLocationUpdates(fusedCallback);
            } catch (Exception ignored) {
            }
        }
        fused = null;
        fusedCallback = null;
        if (locationManager != null) {
            try {
                locationManager.removeUpdates(this);
            } catch (Exception ignored) {
            }
        }
        if (locationThread != null) {
            locationThread.quitSafely();
            locationThread = null;
        }
    }

    private void acquireWake() {
        PowerManager power = (PowerManager) getSystemService(POWER_SERVICE);
        if (power == null || wakeLock != null) {
            return;
        }
        wakeLock = power.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "zentra:shift-gps");
        wakeLock.setReferenceCounted(false);
        wakeLock.acquire();
    }

    private void releaseWake() {
        if (wakeLock != null && wakeLock.isHeld()) {
            wakeLock.release();
        }
        wakeLock = null;
    }

    private static long ageMs(Location loc) {
        return (SystemClock.elapsedRealtimeNanos() - loc.getElapsedRealtimeNanos()) / 1_000_000L;
    }

    private void tick() {
        String token = ShiftTrackingStore.token(this);
        String apiBase = ShiftTrackingStore.apiBase(this);
        String user = ShiftTrackingStore.userId(this);
        if (token.isEmpty() || apiBase.isEmpty()) {
            return;
        }
        cachedToken = token;
        cachedApi = apiBase;
        cachedUser = user;
        queueCurrent(user);
        flush(apiBase, token, user);
    }

    private void queueCurrent(String user) {
        long now = System.currentTimeMillis();
        Location loc = lastFix;
        if (loc == null || ageMs(loc) > NUDGE_AFTER_MS) {
            nudge();
        }
        if (loc == null) {
            return;
        }
        long age = ageMs(loc);
        boolean live = age <= LIVE_FIX_MS;
        boolean changed = loc.getTime() != lastSentFixTime;
        boolean due = now - lastQueuedAt >= SAMPLE_MS;
        if (!live || (!changed && !due)) {
            return;
        }
        long at = loc.getTime() > 0 ? loc.getTime() : now;
        boolean screenOn = false;
        PowerManager power = (PowerManager) getSystemService(POWER_SERVICE);
        if (power != null) {
            screenOn = power.isInteractive();
        }
        try {
            JSONObject point = new JSONObject();
            point.put("latitude", loc.getLatitude());
            point.put("longitude", loc.getLongitude());
            if (loc.hasAccuracy()) {
                point.put("accuracy", Math.round(loc.getAccuracy() * 10f) / 10.0);
            }
            point.put("client_event_id", UUID.randomUUID().toString());
            point.put("screen_on", screenOn);
            point.put("source", "apk");
            point.put("recorded_at", iso(at));
            point.put("t", at);
            ShiftPingQueue.append(this, user, point);
            lastSentFixTime = loc.getTime();
            lastQueuedAt = now;
        } catch (Exception ignored) {
        }
    }

    /** Sube la cola por lotes. 2xx borra lo enviado; sin red queda para el siguiente intento. */
    private void flush(String apiBase, String token, String user) {
        for (int round = 0; round < 10; round++) {
            List<JSONObject> rows = ShiftPingQueue.read(this, user);
            if (rows.isEmpty()) {
                return;
            }
            List<JSONObject> batch = rows.subList(0, Math.min(BATCH, rows.size()));
            List<String> ids = new ArrayList<>();
            JSONArray points = new JSONArray();
            for (JSONObject row : batch) {
                ids.add(row.optString("client_event_id"));
                JSONObject copy = new JSONObject();
                try {
                    copy.put("latitude", row.get("latitude"));
                    copy.put("longitude", row.get("longitude"));
                    if (row.has("accuracy")) {
                        copy.put("accuracy", row.get("accuracy"));
                    }
                    copy.put("client_event_id", row.optString("client_event_id"));
                    copy.put("screen_on", row.optBoolean("screen_on"));
                    copy.put("source", "apk");
                    copy.put("recorded_at", row.optString("recorded_at"));
                } catch (Exception ignored) {
                    continue;
                }
                points.put(copy);
            }
            int code;
            String response;
            try {
                JSONObject body = new JSONObject();
                body.put("points", points);
                String[] result = post(apiBase + "/supervision/shifts/pings", token, body.toString());
                code = Integer.parseInt(result[0]);
                response = result[1];
            } catch (Exception offline) {
                return;
            }
            if (code < 200 || code >= 300) {
                return;
            }
            ShiftPingQueue.remove(this, user, ids);
            if (response == null) {
                return;
            }
        }
    }

    private static String[] post(String url, String token, String body) throws Exception {
        HttpURLConnection conn = (HttpURLConnection) new URL(url).openConnection();
        try {
            conn.setConnectTimeout(10000);
            conn.setReadTimeout(20000);
            conn.setRequestMethod("POST");
            conn.setDoOutput(true);
            conn.setRequestProperty("Authorization", "Bearer " + token);
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("Content-Type", "application/json");
            byte[] bytes = body.getBytes(StandardCharsets.UTF_8);
            conn.setFixedLengthStreamingMode(bytes.length);
            try (OutputStream out = conn.getOutputStream()) {
                out.write(bytes);
            }
            int code = conn.getResponseCode();
            InputStream in = code >= 400 ? conn.getErrorStream() : conn.getInputStream();
            String text = "";
            if (in != null) {
                ByteArrayOutputStream buf = new ByteArrayOutputStream();
                byte[] chunk = new byte[2048];
                int n;
                while ((n = in.read(chunk)) > 0) {
                    buf.write(chunk, 0, n);
                }
                in.close();
                text = buf.toString("UTF-8");
            }
            return new String[] { String.valueOf(code), text };
        } finally {
            conn.disconnect();
        }
    }

    private static String iso(long epochMs) {
        SimpleDateFormat fmt = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", Locale.US);
        fmt.setTimeZone(TimeZone.getTimeZone("UTC"));
        return fmt.format(new Date(epochMs));
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
