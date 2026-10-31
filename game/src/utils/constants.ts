// ============================================
// Oyun sabitleri — tüm tunable değerler burada
// ============================================

// -- Ekran / Layout --
export const GAME_BASE_WIDTH = 800;
export const GAME_BASE_HEIGHT = 600;
export const GAME_VIEW_SCALE = 0.8; // Tek kontrol noktası: >1 daha büyük görünüm, <1 daha küçük görünüm
export const GAME_WIDTH = Math.round(GAME_BASE_WIDTH / GAME_VIEW_SCALE);
export const GAME_HEIGHT = Math.round(GAME_BASE_HEIGHT / GAME_VIEW_SCALE);
export const GAME_BG_COLOR = 0x17191c; // beyaz

// -- Fizik (Matter.js) --
export const GRAVITY_Y = 0.66;
export const CONSTRAINT_ITERATIONS = 12;
export const POSITION_ITERATIONS = 10;
export const VELOCITY_ITERATIONS = 8;

// -- Tava (Pan) --
export const PAN_WIDTH = 170;
export const PAN_HEIGHT = 26;
export const PAN_REST_X = GAME_WIDTH / 2;
export const PAN_REST_Y = GAME_HEIGHT * 0.7;
export const PAN_REST_ANGLE = 0.08; // radyan, dinlenmede hafif sağa yatık
export const PAN_TILT_ANGLE = -0.72; // radyan, sağ uç yukarı kalkar (~41°)
export const PAN_HANDLE_WIDTH = 34; // placeholder sap genişliği
export const PAN_PIVOT_TO_CENTER_X = PAN_WIDTH / 2 + PAN_HANDLE_WIDTH; // elde tutulan sap noktasından tava merkezine yatay mesafe
export const PAN_RAISE_LERP = 0.156; // basılı tutarken açı lerp hızı
export const PAN_DROP_LERP = 0.264; // bırakıldığında geri dönüş lerp hızı
export const PAN_RAISE_Y_OFFSET = -30; // basılı tutarken yukarı kayma (px)
export const PAN_MAX_ANGLE_STEP = 0.0456; // tek framede izin verilen max açı değişimi (radyan)
export const PAN_FRICTION = 0.24; // pancake'in tavaya daha fazla tutunması için artırıldı
export const PAN_STATIC_FRICTION = 0.28;
export const PAN_RESTITUTION = 0.45;
export const PAN_GUIDE_OFFSET_Y = -26; // pan merkezinin üstündeki hafif yönlendirme noktası
export const PAN_VISUAL_METAL_HEIGHT = 12; // sadece görsel metal kalınlığı
export const PAN_VISUAL_EDGE_INSET = 12; // metal kenar kırpma miktarı
export const PAN_VISUAL_FRONT_RIM = 2; // sağ uçta hafif dudak
export const PAN_VISUAL_HANDLE_LENGTH = 58; // sadece görsel sap uzunluğu
export const PAN_VISUAL_HANDLE_THICKNESS = 7; // sadece görsel sap kalınlığı
export const PAN_VISUAL_HANDLE_ATTACH_INSET = 2; // sapın metal gövdeye giriş payı

// -- Tava Fırlatma (Launch) --
export const PAN_LAUNCH_BASE_FORCE = 0.04; // temel fırlatma kuvveti
export const PAN_LAUNCH_ANGLE = -Math.PI / 3; // fırlatma yönü (~-60°, yukarı-sola)
export const PAN_ANGULAR_VELOCITY_FRAMES = 5; // açısal hız hesabı için kaç frame geriye bak

// -- Kol (Arm) Sprite --
export const ARM_ORIGIN_X = 0.76;  // sprite içinde elin sapı tuttuğu nokta (biraz daha sağ hizalama)
export const ARM_ORIGIN_Y = 0.56;  // sprite içinde dikey merkez (biraz daha yukarı)
export const ARM_SCALE    = 0.50;  // sprite ölçeği — pan boyutuna göre ayarlı
export const ARM_OFFSET_X = 0;     // pivot'a ek yatay ince ayar (px)
export const ARM_OFFSET_Y = 0;     // pivot'a ek dikey ince ayar (px)

// -- Pancake Soft-Body (tek satır, ince form) --
export const PANCAKE_NODE_COUNT = 9;
export const PANCAKE_NODE_RADIUS = 6;
export const PANCAKE_NODE_SPACING = 9;             // node teması yoğunlaşır, boşluk kaynaklı delme azalır
export const PANCAKE_NODE_RESTITUTION = 0.42;
export const PANCAKE_NODE_FRICTION = 0.18;
export const PANCAKE_NODE_FRICTION_AIR = 0.0012;
export const PANCAKE_NODE_DENSITY = 0.00169;      // bir %30 daha agir pancake
export const PANCAKE_NEIGHBOR_STIFFNESS = 0.85;    // komşu — şekil iskeleti
export const PANCAKE_SKIP1_STIFFNESS = 0.68;       // 1 atlama — bükülme direnci
export const PANCAKE_SKIP2_STIFFNESS = 0.52;       // 2 atlama — uzunluk korunumu
export const PANCAKE_END_STIFFNESS = 0.45;         // uçtan uca — global boy kilidi
export const PANCAKE_DAMPING = 0.01;
export const PANCAKE_GUIDE_FORCE = 0.00002; // oyuncuya hissettirmeyen çok hafif yardım kuvveti
export const PANCAKE_GUIDE_MAX_DISTANCE = 220; // rehber noktanın etki yarıçapı
export const PANCAKE_GUIDE_DISABLE_ABOVE = 90; // pancake panın çok üstündeyse yardım kapanır
export const PANCAKE_RESPAWN_OFFSET_Y = 250; // pan yüzeyinin üstünde yeniden doğma yüksekliği
export const PANCAKE_DESPAWN_MARGIN = 120; // ekran dışına taşma payı
export const PANCAKE_VISUAL_RADIUS_PAD = 0.7; // çizimde fizik yarıçapına eklenecek ekstra kalınlık
export const PANCAKE_PENETRATION_EPSILON = 0.8; // pan yüzeyinden dışarı itmede ekstra güvenlik payı
export const PANCAKE_PENETRATION_BIAS = 1.04; // çarpışma düzeltmesinde ekstra ayırma katsayısı
export const PANCAKE_PENETRATION_VELOCITY_DAMP = 0.55; // penetrasyon sonrası aşağı hızını azalt
export const PANCAKE_PENETRATION_X_PADDING = 2.5; // slab crossing testinde yan kenarlara güvenlik payı
export const PANCAKE_PENETRATION_PREV_SIDE_EPSILON = 0.6; // önceki frame taraf tespiti toleransı
export const PANCAKE_PENETRATION_PASSES = 2; // bir framede uygulanacak penetrasyon çözüm pass sayısı
export const PANCAKE_COLOR = 0xf25c2a;
export const PANCAKE_STROKE_COLOR = 0xffa371;
export const PANCAKE_SPAWN_X = PAN_REST_X;
export const PANCAKE_SPAWN_Y = PAN_REST_Y - 30;
export const PANCAKE_CENTER_EXPAND_VX = 1.24;
export const PANCAKE_CENTER_EXPAND_VY_JITTER = 0.08;

// -- Flip Detection --
export const FLIP_FULL_ROTATION = Math.PI * 2; // 360° = 1 flip
export const FLIP_SCORE_ROTATION = Math.PI; // 180° = +1 puan

// -- Fail State --
export const FAIL_MARGIN_X = 100; // ekran kenarından bu kadar dışarı çıkarsa fail
export const FAIL_MARGIN_BOTTOM = 50; // ekranın altından bu kadar aşağı düşerse fail

// -- Collision Kategorileri --
export const CATEGORY_PAN = 0x0001;
export const CATEGORY_PANCAKE = 0x0002;

// -- Skor UI --
export const SCORE_TEXT_X = GAME_WIDTH / 2;
export const SCORE_TEXT_Y = 50;

// -- Puan Popup / SFX --
export const POINT_POPUP_RANDOM_X = 34; // +1 metninin yatay rastgele saçılma yarıçapı
export const POINT_POPUP_RANDOM_Y = 14; // +1 metninin dikey rastgele saçılma yarıçapı
export const POINT_POPUP_RISE_Y = 72; // +1 metninin yukarı çıkış mesafesi
export const POINT_POPUP_DURATION = 520; // +1 metninin ekranda kalış süresi (ms)
export const POINT_POPUP_FONT_SIZE = 34;
export const POINT_POPUP_DEPTH = 1100;
export const POINT_SFX_VOLUME = 0.55;

// -- Settings Modal --
export const SETTINGS_SAFE_TOP_DESKTOP = 45;
export const SETTINGS_SAFE_TOP_MOBILE = 120;
export const SETTINGS_BUTTON_SIZE = 44;
export const SETTINGS_BUTTON_RADIUS = 28;
export const SETTINGS_BUTTON_MARGIN_RIGHT = 16;
export const SETTINGS_MODAL_WIDTH = 340;
export const SETTINGS_MODAL_HEIGHT = 250;
export const SETTINGS_UI_DEPTH = 2000;

// -- Main Menu Logo --
export const MENU_LOGO_SCALE_MOBILE = 0.46;
export const MENU_LOGO_SCALE_DESKTOP = 0.58;
export const MENU_PANCAKE_DEPTH = 1500;
export const MENU_PANCAKE_MAX_ACTIVE = 7;
export const MENU_PANCAKE_SPAWN_INTERVAL_MIN_MS = 480;
export const MENU_PANCAKE_SPAWN_INTERVAL_MAX_MS = 980;
export const MENU_PANCAKE_SPAWN_MARGIN_X = 70;
export const MENU_PANCAKE_SPAWN_Y_MIN = -280;
export const MENU_PANCAKE_SPAWN_Y_MAX = -120;
export const MENU_PANCAKE_DESPAWN_MARGIN = 340;
export const MENU_PANCAKE_BASE_VX_MIN = -0.5;
export const MENU_PANCAKE_BASE_VX_MAX = 0.5;
export const MENU_PANCAKE_BASE_VY_MIN = 0.1;
export const MENU_PANCAKE_BASE_VY_MAX = 1.0;
export const MENU_PANCAKE_SPIN_VX_MIN = 0.45;
export const MENU_PANCAKE_SPIN_VX_MAX = 1.2;
export const MENU_PANCAKE_SPIN_VY_MIN = 0.22;
export const MENU_PANCAKE_SPIN_VY_MAX = 0.7;
export const MENU_PANCAKE_NODE_VX_JITTER = 0.14;
export const MENU_PANCAKE_NODE_VY_JITTER = 0.12;
export const MENU_PANCAKE_RANDOM_FORCE_PUSH_X = 0.000035;
export const MENU_PANCAKE_RANDOM_FORCE_PUSH_Y = 0.000045;
export const MENU_PANCAKE_RANDOM_FORCE_TORQUE_Y = 0.00012;
export const MENU_PANCAKE_MOTION_FREQ_MIN = 0.9;
export const MENU_PANCAKE_MOTION_FREQ_MAX = 2.2;

// -- Tutorial UI --
export const TUTORIAL_BUTTON_WIDTH = 190;
export const TUTORIAL_BUTTON_HEIGHT = 56;
export const TUTORIAL_BUTTON_Y = 152;
export const TUTORIAL_POPUP_WIDTH = 460;
export const TUTORIAL_POPUP_HEIGHT = 260;
export const TUTORIAL_UI_DEPTH = 2300;
export const TUTORIAL_POPUP_MOBILE_SCALE = 0.70;

// -- Back To Menu Button --
export const BACK_MENU_BUTTON_RADIUS = 24;
export const BACK_MENU_BUTTON_MARGIN_LEFT = 16;
export const BACK_MENU_BUTTON_DEPTH = 2105;

// -- Typography (Logo uyumlu) --
export const UI_FONT_FAMILY = 'Arial, Helvetica, sans-serif';
export const UI_FONT_STYLE = 'normal';
export const UI_TEXT_PRIMARY_COLOR = '#ffd21f';
export const UI_TEXT_SECONDARY_COLOR = '#fff2cf';
export const UI_TEXT_STROKE_COLOR = '#ff7a00';
