package cloud.wcodex.zentra.supervision;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Build;
import androidx.core.content.ContextCompat;
import com.getcapacitor.JSObject;
import com.getcapacitor.PermissionState;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;
import java.util.ArrayList;

@CapacitorPlugin(
    name = "ShiftTracking",
    permissions = {
        @Permission(
            alias = "location",
            strings = {
                Manifest.permission.ACCESS_FINE_LOCATION,
                Manifest.permission.ACCESS_COARSE_LOCATION
            }
        ),
        @Permission(
            alias = "notifications",
            strings = { Manifest.permission.POST_NOTIFICATIONS }
        )
    }
)
public class ShiftTrackingPlugin extends Plugin {
    @PluginMethod
    public void start(PluginCall call) {
        String apiBase = call.getString("apiBase");
        String token = call.getString("token");
        if (apiBase == null || apiBase.isEmpty() || token == null || token.isEmpty()) {
            call.reject("Faltan apiBase o token");
            return;
        }

        String userId = call.getString("userId", "");
        ShiftTrackingStore.save(getContext(), apiBase.replaceAll("/+$", ""), token, userId == null ? "" : userId);

        ArrayList<String> aliases = new ArrayList<>();
        if (!hasLocation()) {
            aliases.add("location");
        }
        if (needsNotifications()) {
            aliases.add("notifications");
        }
        if (!aliases.isEmpty()) {
            requestPermissionForAliases(aliases.toArray(new String[0]), call, "onTrackingPerms");
            return;
        }

        launchService(call);
    }

    @PermissionCallback
    private void onTrackingPerms(PluginCall call) {
        launchService(call);
    }

    @PluginMethod
    public void stop(PluginCall call) {
        getContext().stopService(new Intent(getContext(), ShiftTrackingService.class));
        ShiftTrackingStore.clear(getContext());
        call.resolve();
    }

    @PluginMethod
    public void updateToken(PluginCall call) {
        String token = call.getString("token");
        if (token != null && !token.isEmpty()) {
            ShiftTrackingStore.saveToken(getContext(), token);
        }
        call.resolve();
    }

    private void launchService(PluginCall call) {
        if (!hasLocation()) {
            call.reject("Se necesita ubicación para el GPS con pantalla apagada.");
            return;
        }
        if (needsNotifications()) {
            call.reject("Se necesita el permiso de notificaciones para el turno.");
            return;
        }

        try {
            ContextCompat.startForegroundService(
                getContext(),
                new Intent(getContext(), ShiftTrackingService.class)
            );
            JSObject ret = new JSObject();
            ret.put("ok", true);
            call.resolve(ret);
        } catch (Exception e) {
            call.reject(e.getMessage() != null ? e.getMessage() : "No se pudo iniciar el GPS de turno.");
        }
    }

    private boolean hasLocation() {
        return ContextCompat.checkSelfPermission(getContext(), Manifest.permission.ACCESS_FINE_LOCATION)
                == PackageManager.PERMISSION_GRANTED
            || ContextCompat.checkSelfPermission(getContext(), Manifest.permission.ACCESS_COARSE_LOCATION)
                == PackageManager.PERMISSION_GRANTED;
    }

    private boolean needsNotifications() {
        return Build.VERSION.SDK_INT >= 33
            && getPermissionState("notifications") != PermissionState.GRANTED
            && ContextCompat.checkSelfPermission(getContext(), Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED;
    }
}
