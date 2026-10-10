package cloud.wcodex.zentra.supervision;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(ShiftTrackingPlugin.class);
        super.onCreate(savedInstanceState);
    }
}
