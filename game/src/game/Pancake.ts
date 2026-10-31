import Phaser from 'phaser';
import {
  PANCAKE_NODE_COUNT,
  PANCAKE_NODE_RADIUS,
  PANCAKE_NODE_SPACING,
  PANCAKE_NODE_RESTITUTION,
  PANCAKE_NODE_FRICTION,
  PANCAKE_NODE_FRICTION_AIR,
  PANCAKE_NODE_DENSITY,
  PANCAKE_NEIGHBOR_STIFFNESS,
  PANCAKE_SKIP1_STIFFNESS,
  PANCAKE_SKIP2_STIFFNESS,
  PANCAKE_END_STIFFNESS,
  PANCAKE_DAMPING,
  PANCAKE_GUIDE_FORCE,
  PANCAKE_GUIDE_MAX_DISTANCE,
  PANCAKE_GUIDE_DISABLE_ABOVE,
  PANCAKE_VISUAL_RADIUS_PAD,
  PANCAKE_PENETRATION_EPSILON,
  PANCAKE_PENETRATION_BIAS,
  PANCAKE_PENETRATION_VELOCITY_DAMP,
  PANCAKE_PENETRATION_X_PADDING,
  PANCAKE_PENETRATION_PREV_SIDE_EPSILON,
  PANCAKE_STROKE_COLOR,
  PANCAKE_CENTER_EXPAND_VX,
  PANCAKE_CENTER_EXPAND_VY_JITTER,
  PAN_WIDTH,
  PAN_HEIGHT,
  PANCAKE_COLOR,
  PANCAKE_SPAWN_X,
  PANCAKE_SPAWN_Y,
  CATEGORY_PAN,
  CATEGORY_PANCAKE,
} from '../utils/constants';

type PancakeSpawnOptions = {
  centerExpand?: boolean;
};

/**
 * Tek satır soft-body pancake.
 *
 * PANCAKE_NODE_COUNT kadar node ve dört katman constraint:
 *  1. Komşu
 *  2. Skip-1
 *  3. Skip-2
 *  4. Uçtan uca
 */
export class Pancake {
  private scene: Phaser.Scene;

  nodes: MatterJS.BodyType[] = [];
  private constraints: MatterJS.ConstraintType[] = [];
  private graphics: Phaser.GameObjects.Graphics;
  private previousPositions: { x: number; y: number }[] = [];
  private previousPanState?: { x: number; y: number; angle: number };
  private readonly centerExpand: boolean;

  private bodyApi: {
    applyForce: (
      body: MatterJS.BodyType,
      position: { x: number; y: number },
      force: { x: number; y: number }
    ) => void;
    setPosition: (
      body: MatterJS.BodyType,
      position: { x: number; y: number }
    ) => void;
    setVelocity: (
      body: MatterJS.BodyType,
      velocity: { x: number; y: number }
    ) => void;
  };

  constructor(
    scene: Phaser.Scene,
    spawnX = PANCAKE_SPAWN_X,
    spawnY = PANCAKE_SPAWN_Y,
    options: PancakeSpawnOptions = {}
  ) {
    this.scene = scene;
    this.centerExpand = options.centerExpand ?? false;
    this.graphics = scene.add.graphics();
    this.bodyApi = this.scene.matter.body as unknown as {
      applyForce: (
        body: MatterJS.BodyType,
        position: { x: number; y: number },
        force: { x: number; y: number }
      ) => void;
      setPosition: (
        body: MatterJS.BodyType,
        position: { x: number; y: number }
      ) => void;
      setVelocity: (
        body: MatterJS.BodyType,
        velocity: { x: number; y: number }
      ) => void;
    };
    this.createNodes(spawnX, spawnY);
    this.createConstraints();
    this.previousPositions = this.nodes.map((n) => ({ x: n.position.x, y: n.position.y }));
  }

  private createNodes(spawnX: number, spawnY: number): void {
    const totalWidth = (PANCAKE_NODE_COUNT - 1) * PANCAKE_NODE_SPACING;
    const startX = this.centerExpand ? spawnX : spawnX - totalWidth / 2;

    for (let i = 0; i < PANCAKE_NODE_COUNT; i++) {
      const node = this.scene.matter.add.circle(
        this.centerExpand ? startX : startX + i * PANCAKE_NODE_SPACING,
        spawnY,
        PANCAKE_NODE_RADIUS,
        {
          restitution: PANCAKE_NODE_RESTITUTION,
          friction: PANCAKE_NODE_FRICTION,
          frictionAir: PANCAKE_NODE_FRICTION_AIR,
          density: PANCAKE_NODE_DENSITY,
          collisionFilter: { category: CATEGORY_PANCAKE, mask: CATEGORY_PAN },
          label: `pancake-${i}`,
        }
      );
      this.nodes.push(node);
    }
  }

  private createConstraints(): void {
    const s = PANCAKE_NODE_SPACING;

    // 1. Komşu
    for (let i = 0; i < PANCAKE_NODE_COUNT - 1; i++) {
      this.addConstraint(this.nodes[i], this.nodes[i + 1], s, PANCAKE_NEIGHBOR_STIFFNESS);
    }

    // 2. Skip-1 (bükülme direnci)
    for (let i = 0; i < PANCAKE_NODE_COUNT - 2; i++) {
      this.addConstraint(this.nodes[i], this.nodes[i + 2], s * 2, PANCAKE_SKIP1_STIFFNESS);
    }

    // 3. Skip-2 (uzunluk korunumu)
    for (let i = 0; i < PANCAKE_NODE_COUNT - 3; i++) {
      this.addConstraint(this.nodes[i], this.nodes[i + 3], s * 3, PANCAKE_SKIP2_STIFFNESS);
    }

    // 4. Uçtan uca (global boy kilidi)
    this.addConstraint(
      this.nodes[0],
      this.nodes[PANCAKE_NODE_COUNT - 1],
      s * (PANCAKE_NODE_COUNT - 1),
      PANCAKE_END_STIFFNESS
    );

    if (this.centerExpand) {
      this.applyCenterExpandImpulse();
    }
  }

  private applyCenterExpandImpulse(): void {
    const midIndex = (PANCAKE_NODE_COUNT - 1) / 2;

    for (let i = 0; i < PANCAKE_NODE_COUNT; i++) {
      const node = this.nodes[i];
      const normalizedOffset = midIndex > 0 ? (i - midIndex) / midIndex : 0;
      this.bodyApi.setVelocity(node, {
        x: normalizedOffset * PANCAKE_CENTER_EXPAND_VX,
        y: Phaser.Math.FloatBetween(-PANCAKE_CENTER_EXPAND_VY_JITTER, PANCAKE_CENTER_EXPAND_VY_JITTER),
      });
    }
  }

  private addConstraint(
    bodyA: MatterJS.BodyType, bodyB: MatterJS.BodyType,
    length: number, stiffness: number
  ): void {
    const c = this.scene.matter.add.constraint(bodyA, bodyB, length, stiffness, { damping: PANCAKE_DAMPING });
    this.constraints.push(c);
  }

  getCenter(): { x: number; y: number } {
    let sumX = 0, sumY = 0;
    for (const n of this.nodes) { sumX += n.position.x; sumY += n.position.y; }
    return { x: sumX / this.nodes.length, y: sumY / this.nodes.length };
  }

  /** Pancake'in uzun ekseni açısı (radyan) — flip tespiti için */
  getOrientationAngle(): number {
    const first = this.nodes[0];
    const last = this.nodes[PANCAKE_NODE_COUNT - 1];
    return Math.atan2(last.position.y - first.position.y, last.position.x - first.position.x);
  }

  applySubtleGuide(targetX: number, targetY: number, panTopY: number): void {
    const center = this.getCenter();

    // Pancake yeterince yukarı çıktıysa veya hedefin üstündeyse yardım kapansın.
    if (center.y < panTopY - PANCAKE_GUIDE_DISABLE_ABOVE || center.y <= targetY) {
      return;
    }

    const dx = targetX - center.x;
    const dy = targetY - center.y;
    const dist = Math.sqrt(dx * dx + dy * dy);
    if (dist <= 0 || dist > PANCAKE_GUIDE_MAX_DISTANCE) {
      return;
    }

    const t = 1 - dist / PANCAKE_GUIDE_MAX_DISTANCE;
    const force = PANCAKE_GUIDE_FORCE * t;
    const fx = (dx / dist) * force;
    const fy = (dy / dist) * force;

    for (const node of this.nodes) {
      this.bodyApi.applyForce(node, node.position, { x: fx, y: fy });
    }
  }

  update(): void {
    this.drawPill();
  }

  setVisualDepth(depth: number): void {
    this.graphics.setDepth(depth);
  }

  resolvePenetrationAgainst(panBody: MatterJS.BodyType): void {
    const hw = PAN_WIDTH / 2;
    const hh = PAN_HEIGHT / 2;
    const separation = PANCAKE_PENETRATION_EPSILON * PANCAKE_PENETRATION_BIAS;
    const xLimit = hw + PANCAKE_NODE_RADIUS * 0.8 + PANCAKE_PENETRATION_X_PADDING;

    const currentPan = {
      x: panBody.position.x,
      y: panBody.position.y,
      angle: panBody.angle,
    };
    const previousPan = this.previousPanState ?? currentPan;

    const currCos = Math.cos(currentPan.angle);
    const currSin = Math.sin(currentPan.angle);
    const prevCos = Math.cos(previousPan.angle);
    const prevSin = Math.sin(previousPan.angle);

    const toLocal = (
      x: number,
      y: number,
      panX: number,
      panY: number,
      cos: number,
      sin: number
    ): { x: number; y: number } => {
      const dx = x - panX;
      const dy = y - panY;
      return {
        x: dx * cos + dy * sin,
        y: -dx * sin + dy * cos,
      };
    };

    const toWorld = (x: number, y: number): { x: number; y: number } => ({
      x: currentPan.x + x * currCos - y * currSin,
      y: currentPan.y + x * currSin + y * currCos,
    });

    for (let i = 0; i < this.nodes.length; i++) {
      const node = this.nodes[i];
      const prev = this.previousPositions[i] ?? { x: node.position.x, y: node.position.y };

      const prevLocal = toLocal(prev.x, prev.y, previousPan.x, previousPan.y, prevCos, prevSin);
      const currLocal = toLocal(node.position.x, node.position.y, currentPan.x, currentPan.y, currCos, currSin);

      const projectOut = (
        targetLocalX: number,
        targetLocalY: number,
        normalLocalX: number,
        normalLocalY: number
      ): void => {
        const world = toWorld(targetLocalX, targetLocalY);
        this.bodyApi.setPosition(node, world);

        const normalWorldX = normalLocalX * currCos - normalLocalY * currSin;
        const normalWorldY = normalLocalX * currSin + normalLocalY * currCos;
        const vn = node.velocity.x * normalWorldX + node.velocity.y * normalWorldY;
        if (vn < 0) {
          this.bodyApi.setVelocity(node, {
            x: node.velocity.x - normalWorldX * vn * (1 - PANCAKE_PENETRATION_VELOCITY_DAMP),
            y: node.velocity.y - normalWorldY * vn * (1 - PANCAKE_PENETRATION_VELOCITY_DAMP),
          });
        }
      };

      const crossedYSlab =
        ((prevLocal.y < -hh && currLocal.y > hh) || (prevLocal.y > hh && currLocal.y < -hh)) &&
        Math.abs(prevLocal.x) <= xLimit &&
        Math.abs(currLocal.x) <= xLimit;

      let corrected = false;

      if (crossedYSlab) {
        const keepAbove = prevLocal.y < 0;
        const targetY = (keepAbove ? -1 : 1) * (hh + separation);
        const targetX = Math.max(-xLimit, Math.min(xLimit, currLocal.x));
        projectOut(targetX, targetY, 0, keepAbove ? -1 : 1);
        corrected = true;
      }

      if (!corrected && Math.abs(currLocal.x) < hw && Math.abs(currLocal.y) < hh) {
        let targetLocalX = currLocal.x;
        let targetLocalY = currLocal.y;
        let normalLocalX = 0;
        let normalLocalY = 0;

        const wasAbove = prevLocal.y <= -hh + PANCAKE_PENETRATION_PREV_SIDE_EPSILON;
        const wasBelow = prevLocal.y >= hh - PANCAKE_PENETRATION_PREV_SIDE_EPSILON;
        const wasLeft = prevLocal.x <= -hw + PANCAKE_PENETRATION_PREV_SIDE_EPSILON;
        const wasRight = prevLocal.x >= hw - PANCAKE_PENETRATION_PREV_SIDE_EPSILON;

        if (wasAbove) {
          normalLocalY = -1;
          targetLocalY = -(hh + separation);
        } else if (wasBelow) {
          normalLocalY = 1;
          targetLocalY = hh + separation;
        } else if (wasLeft) {
          normalLocalX = -1;
          targetLocalX = -(hw + separation);
        } else if (wasRight) {
          normalLocalX = 1;
          targetLocalX = hw + separation;
        } else {
          const penX = hw - Math.abs(currLocal.x);
          const penY = hh - Math.abs(currLocal.y);
          if (penY <= penX) {
            normalLocalY = currLocal.y >= 0 ? 1 : -1;
            targetLocalY = normalLocalY * (hh + separation);
          } else {
            normalLocalX = currLocal.x >= 0 ? 1 : -1;
            targetLocalX = normalLocalX * (hw + separation);
          }
        }

        projectOut(targetLocalX, targetLocalY, normalLocalX, normalLocalY);
      }

      this.previousPositions[i] = { x: node.position.x, y: node.position.y };
    }

    this.previousPanState = currentPan;
  }

  isOutOfBounds(width: number, height: number, margin: number): boolean {
    const minX = -margin;
    const maxX = width + margin;
    const minY = -margin;
    const maxY = height + margin;

    for (const node of this.nodes) {
      const { x, y } = node.position;
      if (x >= minX && x <= maxX && y >= minY && y <= maxY) {
        return false;
      }
    }
    return true;
  }

  /**
   * Node'ların oluşturduğu eksen boyunca hizalanmış pill (yuvarlak dikdörtgen) çizer.
   * Tek satır yapısı için angle-sort yerine üst/alt profil doğru çalışır.
   */
  private drawPill(): void {
    this.graphics.clear();

    const axisAngle = this.getOrientationAngle();
    const cosAxis = Math.cos(axisAngle);
    const sinAxis = Math.sin(axisAngle);

    // Node'ları eksen boyunca sırala (sol → sağ)
    const center = this.getCenter();
    const sorted = [...this.nodes].sort((a, b) => {
      const projA = (a.position.x - center.x) * cosAxis + (a.position.y - center.y) * sinAxis;
      const projB = (b.position.x - center.x) * cosAxis + (b.position.y - center.y) * sinAxis;
      return projA - projB;
    });

    const r = PANCAKE_NODE_RADIUS + PANCAKE_VISUAL_RADIUS_PAD; // pill yarı-kalınlığı
    const perpAngle = axisAngle + Math.PI / 2;
    const cosP = Math.cos(perpAngle);
    const sinP = Math.sin(perpAngle);

    // Üst profil (sol→sağ) + alt profil (sağ→sol)
    const upper = sorted.map(n => ({
      x: n.position.x + cosP * r,
      y: n.position.y + sinP * r,
    }));
    const lower = sorted.map(n => ({
      x: n.position.x - cosP * r,
      y: n.position.y - sinP * r,
    })).reverse();

    const polygon = [...upper, ...lower];

    this.graphics.fillStyle(PANCAKE_COLOR, 0.92);
    this.graphics.fillPoints(polygon, true);
    this.graphics.lineStyle(2, PANCAKE_STROKE_COLOR, 1);
    this.graphics.strokePoints(polygon, true);
  }

  destroy(): void {
    for (const c of this.constraints) this.scene.matter.world.removeConstraint(c);
    for (const n of this.nodes) this.scene.matter.world.remove(n);
    this.graphics.destroy();
  }
}
