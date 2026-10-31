import Phaser from 'phaser';

export class InputManager {
  private scene: Phaser.Scene;
  private enabled = true;
  private isPointerDown = false;
  private isKeyboardDown = false;
  private keyboard?: Phaser.Input.Keyboard.KeyboardPlugin;
  private readonly resetOnBlur = (): void => {
    this.isPointerDown = false;
    this.isKeyboardDown = false;
    this.syncHoldingState();
  };

  /** Oyuncu şu an basılı tutuyor mu */
  isHolding = false;

  /** Basılı tutma süresi (ms) — her pointer up'ta sıfırlanır */
  holdDuration = 0;

  /** Basılı tutmaya başlama zamanı (ms) */
  private holdStartTime = 0;

  constructor(scene: Phaser.Scene) {
    this.scene = scene;
    this.keyboard = this.scene.input.keyboard ?? undefined;

    this.scene.input.on('pointerdown', this.onPointerDown, this);
    this.scene.input.on('pointerup', this.onPointerUp, this);
    this.scene.input.on('pointerupoutside', this.onPointerUp, this);
    window.addEventListener('blur', this.resetOnBlur);
    this.keyboard?.on('keydown-SPACE', this.onKeyboardDown, this);
    this.keyboard?.on('keyup-SPACE', this.onKeyboardUp, this);
    this.keyboard?.on('keydown-UP', this.onKeyboardDown, this);
    this.keyboard?.on('keyup-UP', this.onKeyboardUp, this);
  }

  private onPointerDown(): void {
    if (!this.enabled) {
      return;
    }
    this.isPointerDown = true;
    this.syncHoldingState();
  }

  private onPointerUp(): void {
    this.isPointerDown = false;
    this.syncHoldingState();
  }

  private onKeyboardDown(): void {
    if (!this.enabled || this.isKeyboardDown) {
      return;
    }
    this.isKeyboardDown = true;
    this.syncHoldingState();
  }

  private onKeyboardUp(): void {
    this.isKeyboardDown = false;
    this.syncHoldingState();
  }

  private syncHoldingState(): void {
    const nextHolding = this.enabled && (this.isPointerDown || this.isKeyboardDown);
    if (nextHolding === this.isHolding) {
      return;
    }
    this.isHolding = nextHolding;
    if (nextHolding) {
      this.holdStartTime = this.scene.time.now;
      this.holdDuration = 0;
      return;
    }
    this.holdDuration = this.scene.time.now - this.holdStartTime;
  }

  /** Her frame çağrılır — holdDuration'ı günceller */
  update(): void {
    if (!this.enabled) {
      return;
    }
    if (this.isHolding) {
      this.holdDuration = this.scene.time.now - this.holdStartTime;
    }
  }

  setEnabled(enabled: boolean): void {
    this.enabled = enabled;
    if (!enabled) {
      this.isPointerDown = false;
      this.isKeyboardDown = false;
      this.isHolding = false;
      this.holdDuration = 0;
    }
  }

  destroy(): void {
    this.scene.input.off('pointerdown', this.onPointerDown, this);
    this.scene.input.off('pointerup', this.onPointerUp, this);
    this.scene.input.off('pointerupoutside', this.onPointerUp, this);
    window.removeEventListener('blur', this.resetOnBlur);
    this.keyboard?.off('keydown-SPACE', this.onKeyboardDown, this);
    this.keyboard?.off('keyup-SPACE', this.onKeyboardUp, this);
    this.keyboard?.off('keydown-UP', this.onKeyboardDown, this);
    this.keyboard?.off('keyup-UP', this.onKeyboardUp, this);
  }
}
