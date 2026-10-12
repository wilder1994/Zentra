package cloud.wcodex.zentra.supervision;

import android.content.Context;
import java.io.BufferedReader;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStreamReader;
import java.io.OutputStreamWriter;
import java.io.Writer;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;
import org.json.JSONObject;

/** GPS guardado en el celular mientras no hay red. Un archivo por supervisor; una línea JSON por punto. */
final class ShiftPingQueue {
    static final Object LOCK = new Object();
    private static final int MAX_LINES = 6000;
    private static final long MAX_AGE_MS = 72L * 3600L * 1000L;

    private ShiftPingQueue() {}

    private static File file(Context context, String userId) {
        String owner = userId == null || userId.isEmpty() ? "0" : userId.replaceAll("[^0-9]", "");
        return new File(context.getFilesDir(), "ping_queue_u" + owner + ".jsonl");
    }

    static void append(Context context, String userId, JSONObject point) {
        synchronized (LOCK) {
            try (Writer out = new OutputStreamWriter(new FileOutputStream(file(context, userId), true), StandardCharsets.UTF_8)) {
                out.write(point.toString());
                out.write('\n');
            } catch (Exception ignored) {
            }
        }
    }

    /** Puntos vigentes (máx. 72 h y {@link #MAX_LINES}), del más viejo al más nuevo. */
    static List<JSONObject> read(Context context, String userId) {
        List<JSONObject> rows = new ArrayList<>();
        synchronized (LOCK) {
            File f = file(context, userId);
            if (!f.exists()) {
                return rows;
            }
            long cutoff = System.currentTimeMillis() - MAX_AGE_MS;
            try (BufferedReader in = new BufferedReader(new InputStreamReader(new FileInputStream(f), StandardCharsets.UTF_8))) {
                String line;
                while ((line = in.readLine()) != null) {
                    if (line.trim().isEmpty()) {
                        continue;
                    }
                    try {
                        JSONObject row = new JSONObject(line);
                        if (row.optLong("t", 0) >= cutoff) {
                            rows.add(row);
                        }
                    } catch (Exception ignored) {
                    }
                }
            } catch (Exception ignored) {
            }
        }
        if (rows.size() > MAX_LINES) {
            rows = new ArrayList<>(rows.subList(rows.size() - MAX_LINES, rows.size()));
        }
        return rows;
    }

    /** Quita del archivo los puntos ya enviados (por client_event_id). */
    static void remove(Context context, String userId, List<String> sentIds) {
        synchronized (LOCK) {
            List<JSONObject> keep = new ArrayList<>();
            for (JSONObject row : read(context, userId)) {
                if (!sentIds.contains(row.optString("client_event_id"))) {
                    keep.add(row);
                }
            }
            File f = file(context, userId);
            if (keep.isEmpty()) {
                f.delete();
                return;
            }
            try (Writer out = new OutputStreamWriter(new FileOutputStream(f, false), StandardCharsets.UTF_8)) {
                for (JSONObject row : keep) {
                    out.write(row.toString());
                    out.write('\n');
                }
            } catch (Exception ignored) {
            }
        }
    }
}
