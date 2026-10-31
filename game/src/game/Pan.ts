import Phaser from 'phaser';
import {
  PAN_WIDTH,
  PAN_HEIGHT,
  PAN_REST_ANGLE,
  PAN_TILT_ANGLE,
  PAN_PIVOT_TO_CENTER_X,
  PAN_RAISE_LERP,
  PAN_DROP_LERP,
  PAN_MAX_ANGLE_STEP,
  PAN_FRICTION,
  PAN_STATIC_FRICTION,
  PAN_RESTITUTION,
  PAN_GUIDE_OFFSET_Y,
  PAN_VISUAL_METAL_HEIGHT,
  PAN_VISUAL_EDGE_INSET,
  PAN_VISUAL_FRONT_RIM,
  PAN_ANGULAR_VELOCITY_FRAMES,
  CATEGORY_PAN,
  CATEGORY_PANCAKE,
} from '../utils/constants';

type VelocityAwareBodyApi = {
  setPosition: (
    body: MatterJS.BodyType,
    position: { x: number; y: number },
    updateVelocity?: boolean
  ) => void;
  setAngle: (
    body: MatterJS.BodyType,
    angle: number,
    updateVelocity?: boolean
  ) => void;
};

export class Pan {
  private scene: Phaser.Scene;

  body: MatterJS.BodyType;

  private currentAngle = PAN_REST_ANGLE;
  private currentCenterY = 0;
  private currentCenterX = 0;
  private angleHistory: number[] = [];

  private graphics: Phaser.GameObjects.Graphics;

  // World-space fixed pivot where the pan is held.
  private pivotX = 0;
  private pivotY = 0;

  constructor(scene: Phaser.Scene) {
    this.scene = scene;
    const restCenter = this.getRestCenter();
    this.currentCenterX = restCenter.x;
    this.currentCenterY = restCenter.y;
    this.pivotX = restCenter.x - PAN_PIVOT_TO_CENTER_X;
    this.pivotY = restCenter.y;

    this.body = scene.matter.add.rectangle(
      this.currentCenterX,
      this.currentCenterY,
      PAN_WIDTH,
      PAN_HEIGHT,
      {
        isStatic: true,
        collisionFilter: {
          category: CATEGORY_PAN,
          mask: CATEGORY_PANCAKE,
        },
        friction: PAN_FRICTION,
        frictionStatic: PAN_STATIC_FRICTION,
        restitution: PAN_RESTITUTION,
        label: 'pan',
      }
    );

    this.graphics = scene.add.graphics();

    for (let i = 0; i < PAN_ANGULAR_VELOCITY_FRAMES; i++) {
      this.angleHistory.push(PAN_REST_ANGLE);
    }
  }

  update(isHolding: boolean): void {
    const restCenter = this.getRestCenter();
    this.pivotX = restCenter.x - PAN_PIVOT_TO_CENTER_X;
    this.pivotY = restCenter.y;

    const targetAngle = isHolding ? PAN_TILT_ANGLE : PAN_REST_ANGLE;
    const lerp = isHolding ? PAN_RAISE_LERP : PAN_DROP_LERP;

    const angleDelta = (targetAngle - this.currentAngle) * lerp;
    const clampedDelta = Phaser.Math.Clamp(
      angleDelta,
      -PAN_MAX_ANGLE_STEP,
      PAN_MAX_ANGLE_STEP
    );
    this.currentAngle += clampedDelta;

    this.angleHistory.push(this.currentAngle);
    if (this.angleHistory.length > PAN_ANGULAR_VELOCITY_FRAMES) {
      this.angleHistory.shift();
    }

    const cos = Math.cos(this.currentAngle);
    const sin = Math.sin(this.currentAngle);
    const rotatedX = PAN_PIVOT_TO_CENTER_X * cos;
    const rotatedY = PAN_PIVOT_TO_CENTER_X * sin;

    const bodyX = this.pivotX + rotatedX;
    const bodyY = this.pivotY + rotatedY;
    this.currentCenterX = bodyX;
    this.currentCenterY = bodyY;

    const bodyApi = this.scene.matter.body as unknown as VelocityAwareBodyApi;
    bodyApi.setPosition(this.body, { x: bodyX, y: bodyY }, true);
    bodyApi.setAngle(this.body, this.currentAngle, true);

    this.drawPanMetal(bodyX, bodyY);
  }

  private getRestCenter(): { x: number; y: number } {
    const view = this.scene.scale.getViewPort();
    return {
      x: view.x + view.width / 2,
      y: view.y + view.height * 0.7,
    };
  }

  getAngularVelocity(): number {
    if (this.angleHistory.length < 2) return 0;
    const oldest = this.angleHistory[0];
    const newest = this.angleHistory[this.angleHistory.length - 1];
    return (newest - oldest) / this.angleHistory.length;
  }

  getTopY(): number {
    return this.currentCenterY - PAN_HEIGHT / 2;
  }

  getCenter(): { x: number; y: number } {
    return { x: this.currentCenterX, y: this.currentCenterY };
  }

  getGuidePoint(): { x: number; y: number } {
    return { x: this.currentCenterX, y: this.currentCenterY + PAN_GUIDE_OFFSET_Y };
  }

  private rotatePoint(
    px: number,
    py: number,
    cx: number,
    cy: number,
    cos: number,
    sin: number
  ): { x: number; y: number } {
    const dx = px - cx;
    const dy = py - cy;
    return {
      x: cx + dx * cos - dy * sin,
      y: cy + dx * sin + dy * cos,
    };
  }

  private drawPanMetal(centerX: number, centerY: number): void {
    this.graphics.clear();
    const cos = Math.cos(this.currentAngle);
    const sin = Math.sin(this.currentAngle);

    // Only metal body is drawn. Handle now belongs to the arm asset.
    // Shape intent: shallow bowl silhouette (\___/).
    const metalHalfW = PAN_WIDTH / 2;
    const edgeInset = PAN_VISUAL_EDGE_INSET;
    const frontRim = PAN_VISUAL_FRONT_RIM;
    const lipHeight = PAN_VISUAL_METAL_HEIGHT * 0.58;
    const bowlDepth = PAN_VISUAL_METAL_HEIGHT * 0.68;
    const floorHalfW = metalHalfW - edgeInset * 1.7;

    const panLocal = [
      { x: -metalHalfW, y: -lipHeight },
      { x: -metalHalfW + edgeInset * 0.75, y: -lipHeight * 0.5 },
      { x: -metalHalfW + edgeInset * 1.2, y: bowlDepth * 0.18 },
      { x: -floorHalfW, y: bowlDepth },
      { x: floorHalfW, y: bowlDepth },
      { x: metalHalfW - edgeInset * 1.05, y: bowlDepth * 0.22 + frontRim },
      { x: metalHalfW - edgeInset * 0.6, y: -lipHeight * 0.5 - frontRim },
      { x: metalHalfW, y: -lipHeight - frontRim },
    ];

    const panCorners = panLocal.map((p) =>
      this.rotatePoint(centerX + p.x, centerY + p.y, centerX, centerY, cos, sin)
    );

    const handleStart = this.rotatePoint(centerX - metalHalfW - 58, centerY - 3, centerX, centerY, cos, sin);
    const handleEnd = this.rotatePoint(centerX - metalHalfW + 5, centerY - 3, centerX, centerY, cos, sin);
    this.graphics.lineStyle(7, 0xf5f2e9, 1);
    this.graphics.lineBetween(handleStart.x, handleStart.y, handleEnd.x, handleEnd.y);
    this.graphics.fillStyle(0xf5f2e9, 1);
    this.graphics.fillPoints(panCorners, true);
    this.graphics.lineStyle(2, 0xf5f2e9, 1);
    this.graphics.strokePoints(panCorners, true);
  }

  destroy(): void {
    this.scene.matter.world.remove(this.body);
    this.graphics.destroy();
  }
}
