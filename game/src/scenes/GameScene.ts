import Phaser from 'phaser';
import { InputManager } from '../game/InputManager';
import { Pan } from '../game/Pan';
import { Pancake } from '../game/Pancake';
import {
  GAME_BG_COLOR,
  PANCAKE_RESPAWN_OFFSET_Y,
  PANCAKE_DESPAWN_MARGIN,
  PANCAKE_PENETRATION_PASSES,
  FLIP_SCORE_ROTATION,
  POINT_POPUP_RANDOM_X,
  POINT_POPUP_RANDOM_Y,
  POINT_POPUP_RISE_Y,
  POINT_POPUP_DURATION,
  POINT_POPUP_FONT_SIZE,
  POINT_POPUP_DEPTH,
  POINT_SFX_VOLUME,
  UI_FONT_FAMILY,
} from '../utils/constants';

type SettingsState = {
  music: boolean;
  fx: boolean;
  haptics: boolean;
};

type HapticType = 'light' | 'medium' | 'heavy' | 'success' | 'error';

type TutorialStep = 'none' | 'spawn_prompt' | 'wait_pan_touch' | 'wait_flip' | 'completed';

export class GameScene extends Phaser.Scene {
  private static readonly SETTINGS_STORAGE_KEY = 'blynine.settings.v1';
  private hasUserInteracted = false;
  private uiReady = false;

  private inputManager!: InputManager;
  private pan?: Pan;
  private pancake?: Pancake;

  private score = 0;
  private previousPancakeAngle?: number;
  private flipProgress = 0;
  private hasSubmittedScoreForCurrentPancake = false;

  private settings: SettingsState = { music: true, fx: true, haptics: true };
  private isSettingsOpen = false;

  private backgroundMusic?: Phaser.Sound.BaseSound;

  // -- Menu state --
  private gameState: 'menu' | 'game' | 'tutorial' = 'menu';
  private menuOverlay?: Phaser.GameObjects.Rectangle;
  private menuContainer?: Phaser.GameObjects.Container;
  private tutorialStep: TutorialStep = 'none';
  private tutorialOnClose?: () => void;
  private isTutorialPopupOpen = false;
  private readonly handleTutorialCloseRequest = (): void => {
    if (!this.isTutorialPopupOpen) {
      return;
    }
    this.triggerHaptic('light');
    this.closeTutorialPopup();
  };

  constructor() {
    super({ key: 'GameScene' });
  }

  public isUiReady(): boolean {
    return this.uiReady;
  }

  preload(): void {
    const base = import.meta.env.BASE_URL;
    this.load.audio('point', `${base}assets/Point.mp3`);
    this.load.audio('bgm', `${base}assets/Pancake Main Theme.mp3`);
  }

  create(): void {
    this.settings = this.loadSettings();
    this.cameras.main.setRoundPixels(true);

    this.inputManager = new InputManager(this);
    this.inputManager.setEnabled(false);
    this.createMenuUI();
    this.createSettingsUI();
    this.createTutorialUI();
    this.layoutTutorialUI();
    this.createMusic();
    this.refreshScoreUI();
    this.emitGameStateChangeEvent();
    this.emitSettingsVisibilityEvent();
    this.emitTutorialPopupEvent(false);
    this.uiReady = true;
    window.dispatchEvent(new Event('pancake:ready'));

    this.input.on('pointerdown', this.handlePointerDown, this);
    this.input.keyboard?.on('keydown-SPACE', this.handleKeyboardSpawn, this);
    this.input.keyboard?.on('keydown-UP', this.handleKeyboardSpawn, this);
    this.events.on(Phaser.Scenes.Events.PRE_UPDATE, this.handlePreUpdate, this);
    this.scale.on(Phaser.Scale.Events.RESIZE, this.handleResize, this);
    this.matter.world.on('collisionstart', this.handleCollisionStart, this);
    window.addEventListener('pancake:tutorialclose', this.handleTutorialCloseRequest);

    this.events.once(Phaser.Scenes.Events.SHUTDOWN, () => {
      this.input.off('pointerdown', this.handlePointerDown, this);
      this.input.keyboard?.off('keydown-SPACE', this.handleKeyboardSpawn, this);
      this.input.keyboard?.off('keydown-UP', this.handleKeyboardSpawn, this);
      this.inputManager.destroy();
      this.events.off(Phaser.Scenes.Events.PRE_UPDATE, this.handlePreUpdate, this);
      this.scale.off(Phaser.Scale.Events.RESIZE, this.handleResize, this);
      this.matter.world.off('collisionstart', this.handleCollisionStart, this);
      window.removeEventListener('pancake:tutorialclose', this.handleTutorialCloseRequest);
      this.uiReady = false;

      if (this.pancake) {
        this.pancake.destroy();
        this.pancake = undefined;
      }
      if (this.pan) {
        this.pan.destroy();
        this.pan = undefined;
      }

      this.menuOverlay?.destroy();
      this.menuContainer?.destroy();

      this.emitTutorialPopupEvent(false);

      if (this.backgroundMusic) {
        this.backgroundMusic.stop();
        this.backgroundMusic.destroy();
        this.backgroundMusic = undefined;
      }
    });
  }

  public startGameFromUi(): void {
    this.hasUserInteracted = true;
    this.applyMusicSetting();
    this.startGame();
  }

  public startTutorialFromUi(): void {
    this.hasUserInteracted = true;
    this.applyMusicSetting();
    this.startTutorial();
  }

  public toggleSettingsFromUi(): void {
    this.hasUserInteracted = true;
    this.applyMusicSetting();
    if (this.isSettingsOpen) {
      return;
    }
    this.openSettingsModal();
  }

  public returnToMainMenuFromUi(): void {
    this.triggerHaptic('light');
    this.returnToMainMenu();
  }

  private emitGameStateChangeEvent(): void {
    window.dispatchEvent(
      new CustomEvent('pancake:statechange', {
        detail: { state: this.gameState },
      })
    );
  }

  private emitSettingsVisibilityEvent(): void {
    window.dispatchEvent(
      new CustomEvent('pancake:settingschange', {
        detail: { isOpen: this.isSettingsOpen },
      })
    );
  }

  private emitTutorialPopupEvent(isOpen: boolean, text = ''): void {
    window.dispatchEvent(
      new CustomEvent('pancake:tutorialpopup', {
        detail: { isOpen, text },
      })
    );
  }

  private handleKeyboardSpawn(event: KeyboardEvent): void {
    if (!event.repeat && !(event.target instanceof HTMLInputElement)) this.handlePointerDown();
  }

  private handlePointerDown(): void {
    this.hasUserInteracted = true;
    // Browser audio lock'u ilk kullanıcı etkileşiminde açılır.
    this.applyMusicSetting();

    if (this.gameState !== 'game' && this.gameState !== 'tutorial') {
      return;
    }

    if (this.isSettingsOpen) {
      return;
    }

    if (this.isTutorialPopupOpen) {
      return;
    }

    if (this.pancake) {
      return;
    }

    if (!this.pan) {
      return;
    }

    const panCenter = this.pan.getCenter();
    const spawnY = this.pan.getTopY() - PANCAKE_RESPAWN_OFFSET_Y;
    this.pancake = new Pancake(this, panCenter.x, spawnY, { centerExpand: true });

    this.score = 0;
    this.refreshScoreUI();
    this.previousPancakeAngle = this.pancake.getOrientationAngle();
    this.flipProgress = 0;
    this.hasSubmittedScoreForCurrentPancake = false;

    if (this.gameState === 'tutorial' && this.tutorialStep === 'spawn_prompt') {
      this.tutorialStep = 'wait_pan_touch';
    }
  }

  private handlePreUpdate(): void {
    if (this.gameState !== 'game' && this.gameState !== 'tutorial') {
      return;
    }

    if (this.isTutorialPopupOpen || this.isSettingsOpen) return;

    this.inputManager.update();
    if (!this.pan) {
      return;
    }
    this.pan.update(this.inputManager.isHolding);

    if (!this.pancake) {
      return;
    }

    const guidePoint = this.pan.getGuidePoint();
    this.pancake.applySubtleGuide(guidePoint.x, guidePoint.y, this.pan.getTopY());
  }

  update(): void {
    if (this.gameState === 'menu' || this.isSettingsOpen || this.isTutorialPopupOpen) return;

    if (!this.pancake) {
      return;
    }
    if (!this.pan) {
      return;
    }

    for (let i = 0; i < PANCAKE_PENETRATION_PASSES; i++) {
      this.pancake.resolvePenetrationAgainst(this.pan.body);
    }

    this.updateFlipScore();
    this.pancake.update();

    const view = this.scale.getViewPort();
    if (this.pancake.isOutOfBounds(view.width, view.height, PANCAKE_DESPAWN_MARGIN)) {
      if (this.gameState === 'game' && !this.hasSubmittedScoreForCurrentPancake) {
        this.submitFinalScore(this.score);
        this.hasSubmittedScoreForCurrentPancake = true;
      }
      this.pancake.destroy();
      this.pancake = undefined;
      this.previousPancakeAngle = undefined;
      this.flipProgress = 0;
    }
  }

  private emitScoreChangeEvent(pulse: boolean = false): void {
    window.dispatchEvent(
      new CustomEvent('pancake:scorechange', {
        detail: { score: this.score, pulse },
      })
    );
  }

  private refreshScoreUI(pulse: boolean = false): void {
    this.emitScoreChangeEvent(pulse);
  }

  private updateFlipScore(): void {
    if (!this.pancake) {
      return;
    }

    const currentAngle = this.pancake.getOrientationAngle();
    if (this.previousPancakeAngle === undefined) {
      this.previousPancakeAngle = currentAngle;
      return;
    }

    const delta = Phaser.Math.Angle.Wrap(currentAngle - this.previousPancakeAngle);
    this.previousPancakeAngle = currentAngle;
    this.flipProgress += delta;

    let gained = 0;
    while (this.flipProgress >= FLIP_SCORE_ROTATION) {
      gained += 1;
      this.flipProgress -= FLIP_SCORE_ROTATION;
    }
    while (this.flipProgress <= -FLIP_SCORE_ROTATION) {
      gained += 1;
      this.flipProgress += FLIP_SCORE_ROTATION;
    }

    if (gained <= 0) {
      return;
    }

    this.score += gained;
    this.refreshScoreUI(true);

    if (this.gameState === 'tutorial' && this.tutorialStep === 'wait_flip') {
      this.tutorialStep = 'completed';
      this.showTutorialPopup(
        'Puiku! Kiekvienas 180° apsivertimas – vienas taškas.\nKiek kartų pavyks išversti?'
      );
    }

    const center = this.pancake.getCenter();
    for (let i = 0; i < gained; i++) {
      this.spawnPointPopup(center.x, center.y);
      if (this.settings.fx && this.cache.audio.exists('point')) {
        this.sound.play('point', { volume: POINT_SFX_VOLUME });
      }
      this.triggerHaptic('light');
    }
  }

  private spawnPointPopup(centerX: number, centerY: number): void {
    const x = centerX + Phaser.Math.FloatBetween(-POINT_POPUP_RANDOM_X, POINT_POPUP_RANDOM_X);
    const y = centerY + Phaser.Math.FloatBetween(-POINT_POPUP_RANDOM_Y, POINT_POPUP_RANDOM_Y);

    const popup = this.add.text(x, y, '+1', {
      fontFamily: UI_FONT_FAMILY,
      fontStyle: '600',
      fontSize: `${POINT_POPUP_FONT_SIZE - 4}px`,
      color: '#f5f2e9',
    });
    popup.setAlpha(0.92);
    popup.setOrigin(0.5, 0.5);
    popup.setDepth(POINT_POPUP_DEPTH);

    this.tweens.add({
      targets: popup,
      y: y - POINT_POPUP_RISE_Y,
      alpha: 0,
      scale: 1.12,
      duration: POINT_POPUP_DURATION,
      ease: 'Cubic.Out',
      onComplete: () => popup.destroy(),
    });
  }

  private createMusic(): void {
    if (!this.cache.audio.exists('bgm')) {
      console.warn('[pancake] Missing bgm audio in cache, skipping music init.');
      return;
    }
    this.backgroundMusic = this.sound.add('bgm', { loop: true, volume: 0.42 });
    this.applyMusicSetting();
    this.sound.once('unlocked', () => {
      this.applyMusicSetting();
    });
  }

  private applyMusicSetting(): void {
    if (!this.backgroundMusic || !this.hasUserInteracted) {
      return;
    }

    if (!this.settings.music) {
      if (this.backgroundMusic.isPlaying) {
        this.backgroundMusic.stop();
      }
      return;
    }

    if (this.sound.locked) {
      return;
    }

    if (!this.backgroundMusic.isPlaying) {
      this.backgroundMusic.play();
    }
  }

  private createMenuUI(): void {
    const view = this.scale.getViewPort();
    const cx = view.x + view.width / 2;
    const cy = view.y + view.height / 2;

    this.menuOverlay = this.add
      .rectangle(view.x, view.y, view.width, view.height, GAME_BG_COLOR, 1)
      .setOrigin(0, 0)
      .setDepth(1500)
      .setInteractive();

    this.menuContainer = this.add.container(cx, cy).setDepth(1501);
  }

  private returnToMainMenu(): void {
    if (this.gameState === 'menu') {
      return;
    }

    this.gameState = 'menu';
    this.tutorialStep = 'none';
    this.setWorldPaused(false);

    if (this.isTutorialPopupOpen) {
      this.tutorialOnClose = undefined;
      this.closeTutorialPopup();
    }
    if (this.isSettingsOpen) {
      this.closeSettingsModal();
    }

    this.inputManager.setEnabled(false);

    if (this.pancake) {
      this.pancake.destroy();
      this.pancake = undefined;
    }
    if (this.pan) {
      this.pan.destroy();
      this.pan = undefined;
    }

    this.previousPancakeAngle = undefined;
    this.flipProgress = 0;

    this.menuOverlay?.destroy();
    this.menuContainer?.destroy();
    this.menuOverlay = undefined;
    this.menuContainer = undefined;

    this.createMenuUI();
    this.emitGameStateChangeEvent();
  }

  private createTutorialUI(): void {
    // Tutorial popup is rendered in DOM for crisp text on all devices.
  }

  private showTutorialPopup(text: string, onClose?: () => void): void {
    this.tutorialOnClose = onClose;
    this.isTutorialPopupOpen = true;
    this.emitTutorialPopupEvent(true, text);
    this.setWorldPaused(true);
    this.inputManager.setEnabled(false);
  }

  private closeTutorialPopup(): void {
    if (!this.isTutorialPopupOpen) {
      return;
    }

    this.isTutorialPopupOpen = false;
    this.emitTutorialPopupEvent(false);
    this.setWorldPaused(false);

    if (!this.isSettingsOpen && (this.gameState === 'game' || this.gameState === 'tutorial')) {
      this.inputManager.setEnabled(true);
    }

    const onClose = this.tutorialOnClose;
    this.tutorialOnClose = undefined;
    onClose?.();
  }

  private setWorldPaused(paused: boolean): void {
    const world = this.matter.world as any;
    if (paused) {
      if (typeof world.pause === 'function') {
        world.pause();
        return;
      }
      if (world.engine?.timing) {
        world.engine.timing.timeScale = 0;
      }
      return;
    }

    if (typeof world.resume === 'function') {
      world.resume();
      return;
    }
    if (world.engine?.timing) {
      world.engine.timing.timeScale = 1;
    }
  }

  private handleCollisionStart(event: any): void {
    if (
      this.gameState !== 'tutorial' ||
      this.tutorialStep !== 'wait_pan_touch' ||
      !this.pan ||
      !this.pancake ||
      this.isTutorialPopupOpen
    ) {
      return;
    }

    const panBody = this.pan.body;
    for (const pair of event.pairs ?? []) {
      const bodyA = pair.bodyA as MatterJS.BodyType;
      const bodyB = pair.bodyB as MatterJS.BodyType;
      const hitPan = bodyA === panBody || bodyB === panBody;
      if (!hitPan) {
        continue;
      }

      const other = bodyA === panBody ? bodyB : bodyA;
      if (this.pancake.nodes.includes(other)) {
        this.tutorialStep = 'wait_flip';
        this.showTutorialPopup('Paspausk ir palaikyk – keptuvė pakils.\nAtleisk ir mėgink išversti blyną!');
        break;
      }
    }
  }

  private startTutorial(): void {
    if (this.gameState !== 'menu') return;
    this.gameState = 'tutorial';
    this.emitGameStateChangeEvent();
    this.tutorialStep = 'spawn_prompt';
    this.pan = new Pan(this);

    this.tweens.add({
      targets: [this.menuOverlay, this.menuContainer],
      alpha: 0,
      duration: 320,
      ease: 'Power2.In',
      onComplete: () => {
        this.menuOverlay?.destroy();
        this.menuContainer?.destroy();
        this.menuOverlay = undefined;
        this.menuContainer = undefined;
      },
    });

    this.showTutorialPopup('Paliesk žaidimo lauką arba spausk tarpo klavišą.\nĮ keptuvę įkris pirmas blynas.');
  }

  private startGame(): void {
    if (this.gameState !== 'menu') return;
    this.gameState = 'game';
    this.emitGameStateChangeEvent();
    this.tutorialStep = 'none';
    this.pan = new Pan(this);
    this.inputManager.setEnabled(true);

    // Fade out menu elements
    this.tweens.add({
      targets: [this.menuOverlay, this.menuContainer],
      alpha: 0,
      duration: 320,
      ease: 'Power2.In',
      onComplete: () => {
        this.menuOverlay?.destroy();
        this.menuContainer?.destroy();
        this.menuOverlay = undefined;
        this.menuContainer = undefined;
      },
    });

  }

  private createSettingsUI(): void {
    this.emitPreferences();
  }

  private emitPreferences(): void {
    window.dispatchEvent(new CustomEvent('pancake:preferenceschange', { detail: { ...this.settings } }));
  }

  public updateSettingFromUi(key: keyof SettingsState, enabled: boolean): void {
    this.hasUserInteracted = true;
    this.settings[key] = enabled;
    this.saveSettings();
    this.emitPreferences();
    if (key === 'music') this.applyMusicSetting();
    this.triggerHaptic('light');
  }

  public closeSettingsFromUi(): void {
    this.closeSettingsModal();
  }

  private openSettingsModal(): void {
    if (this.isSettingsOpen) return;
    this.isSettingsOpen = true;
    this.setWorldPaused(true);
    this.inputManager.setEnabled(false);
    this.emitPreferences();
    this.emitSettingsVisibilityEvent();
  }

  private closeSettingsModal(): void {
    if (!this.isSettingsOpen) return;
    this.isSettingsOpen = false;
    this.setWorldPaused(this.isTutorialPopupOpen);
    if (!this.isTutorialPopupOpen && this.gameState !== 'menu') this.inputManager.setEnabled(true);
    this.emitSettingsVisibilityEvent();
  }

  private handleResize(): void {
    const view = this.scale.getViewPort();
    this.menuOverlay?.setSize(view.width, view.height);
    this.layoutTutorialUI();
  }

  private layoutTutorialUI(): void {
    // DOM tutorial popup handles its own responsive layout.
  }

  private loadSettings(): SettingsState {
    const fallback: SettingsState = { music: true, fx: true, haptics: true };
    try {
      const raw = window.localStorage.getItem(GameScene.SETTINGS_STORAGE_KEY);
      if (!raw) {
        return fallback;
      }
      const parsed = JSON.parse(raw) as Partial<SettingsState>;
      return {
        music: typeof parsed.music === 'boolean' ? parsed.music : fallback.music,
        fx: typeof parsed.fx === 'boolean' ? parsed.fx : fallback.fx,
        haptics: typeof parsed.haptics === 'boolean' ? parsed.haptics : fallback.haptics,
      };
    } catch {
      return fallback;
    }
  }

  private saveSettings(): void {
    try {
      window.localStorage.setItem(GameScene.SETTINGS_STORAGE_KEY, JSON.stringify(this.settings));
    } catch {
      // Browser privacy settings may disable persistence; in-memory settings still work.
    }
  }

  private submitFinalScore(score: number): void {
    try {
      const appWindow = window as Window & { submitScore?: (value: number) => void };
      if (typeof appWindow.submitScore === 'function') return appWindow.submitScore(score);
      const parentWindow = window.parent as Window & { submitScore?: (value: number) => void };
      if (parentWindow !== window && typeof parentWindow.submitScore === 'function') parentWindow.submitScore(score);
    } catch {
      // An optional host bridge must never interrupt standalone gameplay.
    }
  }

  private triggerHaptic(type: HapticType): void {
    if (!this.settings.haptics) return;
    try {
      const appWindow = window as Window & { triggerHaptic?: (type: HapticType) => void };
      if (typeof appWindow.triggerHaptic === 'function') return appWindow.triggerHaptic(type);
      const parentWindow = window.parent as Window & { triggerHaptic?: (type: HapticType) => void };
      if (parentWindow !== window && typeof parentWindow.triggerHaptic === 'function') return parentWindow.triggerHaptic(type);
    } catch {
      // Cross-origin embeddings cannot access parent bridges.
    }
    if (typeof navigator.vibrate === 'function') navigator.vibrate(type === 'light' ? 14 : 28);
  }
}
