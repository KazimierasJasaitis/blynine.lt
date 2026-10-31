import Phaser from 'phaser';
import { GameScene } from './scenes/GameScene';
import {
  GAME_BG_COLOR,
  GRAVITY_Y,
  CONSTRAINT_ITERATIONS,
  POSITION_ITERATIONS,
  VELOCITY_ITERATIONS,
} from './utils/constants';

const renderResolution = Math.min(window.devicePixelRatio || 1, 3);

const config: Phaser.Types.Core.GameConfig = {
  type: Phaser.AUTO,
  parent: 'game-container',
  width: window.innerWidth,
  height: window.innerHeight,
  autoRound: true,
  backgroundColor: GAME_BG_COLOR,
  render: {
    antialias: true,
    roundPixels: true,
  },
  scale: {
    mode: Phaser.Scale.RESIZE,
    autoCenter: Phaser.Scale.NO_CENTER,
    expandParent: false,
  },
  physics: {
    default: 'matter',
    matter: {
      gravity: { x: 0, y: GRAVITY_Y },
      debug: false,
      constraintIterations: CONSTRAINT_ITERATIONS,
      positionIterations: POSITION_ITERATIONS,
      velocityIterations: VELOCITY_ITERATIONS,
    },
  },
  scene: [GameScene],
};

(
  config as Phaser.Types.Core.GameConfig & {
    resolution: number;
  }
).resolution = renderResolution;

const game = new Phaser.Game(config);

const getGameScene = (): GameScene | undefined => {
  const scene = game.scene.keys.GameScene;
  return scene instanceof GameScene ? scene : undefined;
};

const runWhenSceneReady = (run: (scene: GameScene) => void): void => {
  const scene = getGameScene();
  if (scene?.isUiReady()) run(scene);
};

const menuUi = document.getElementById('menu-ui');
const backButton = document.getElementById('back-button');
const playButton = document.getElementById('play-button');
const tutorialButton = document.getElementById('tutorial-button');
const settingsButton = document.getElementById('settings-button') as HTMLButtonElement;
const scoreHud = document.getElementById('score-hud');
const scoreValue = document.getElementById('score-value');
const tutorialDialog = document.getElementById('tutorial-dialog') as HTMLDialogElement;
const tutorialText = document.getElementById('tutorial-text');
const tutorialOk = document.getElementById('tutorial-ok');
const settingsDialog = document.getElementById('settings-dialog') as HTMLDialogElement;
const loadingStatus = document.getElementById('loading-status');
let currentState: 'menu' | 'game' | 'tutorial' = 'menu';
let isSettingsOpen = false;

const syncUi = (): void => {
  const inMenu = currentState === 'menu';
  menuUi?.classList.toggle('hidden', !inMenu);
  backButton?.classList.toggle('hidden', inMenu);
  scoreHud?.classList.toggle('hidden', inMenu || isSettingsOpen);
  document.getElementById('menu-footer')?.classList.toggle('hidden', !inMenu);
  document.getElementById('game-hint')?.classList.toggle('hidden', inMenu);
  document.body.classList.toggle('is-playing', !inMenu);
  settingsButton.disabled = isSettingsOpen;
  settingsButton.setAttribute('aria-expanded', String(isSettingsOpen));
};

const markReady = (): void => {
  for (const id of ['play-button', 'tutorial-button', 'settings-button']) {
    (document.getElementById(id) as HTMLButtonElement).disabled = false;
  }
  if (loadingStatus) loadingStatus.textContent = 'Keptuvė įkaitusi.';
};
window.addEventListener('pancake:ready', markReady);
if (getGameScene()?.isUiReady()) markReady();

const requestTutorialClose = (event?: Event): void => {
  event?.preventDefault();
  window.dispatchEvent(new CustomEvent('pancake:tutorialclose'));
};
const closeSettings = (): void => runWhenSceneReady((scene) => scene.closeSettingsFromUi());

playButton?.addEventListener('click', () => runWhenSceneReady((scene) => scene.startGameFromUi()));
tutorialButton?.addEventListener('click', () => runWhenSceneReady((scene) => scene.startTutorialFromUi()));
settingsButton.addEventListener('click', () => runWhenSceneReady((scene) => scene.toggleSettingsFromUi()));
backButton?.addEventListener('click', () => runWhenSceneReady((scene) => scene.returnToMainMenuFromUi()));
tutorialOk?.addEventListener('click', requestTutorialClose);
tutorialDialog.addEventListener('cancel', requestTutorialClose);
settingsDialog.addEventListener('cancel', (event) => { event.preventDefault(); closeSettings(); });
document.getElementById('settings-close')?.addEventListener('click', closeSettings);
document.getElementById('settings-done')?.addEventListener('click', closeSettings);
settingsDialog.addEventListener('click', (event) => {
  if (event.target !== settingsDialog) return;
  const bounds = settingsDialog.getBoundingClientRect();
  if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeSettings();
});

for (const key of ['music', 'fx', 'haptics'] as const) {
  const input = document.getElementById(`setting-${key}`) as HTMLInputElement;
  input.addEventListener('change', () => runWhenSceneReady((scene) => scene.updateSettingFromUi(key, input.checked)));
}

window.addEventListener('pancake:preferenceschange', (event: Event) => {
  const settings = (event as CustomEvent<{ music: boolean; fx: boolean; haptics: boolean }>).detail;
  for (const key of ['music', 'fx', 'haptics'] as const) {
    (document.getElementById(`setting-${key}`) as HTMLInputElement).checked = settings[key];
  }
});

window.addEventListener('pancake:statechange', (event: Event) => {
  const state = (event as CustomEvent<{ state: 'menu' | 'game' | 'tutorial' }>).detail.state;
  currentState = state;
  syncUi();
});
window.addEventListener('pancake:settingschange', (event: Event) => {
  isSettingsOpen = (event as CustomEvent<{ isOpen: boolean }>).detail.isOpen;
  if (isSettingsOpen && !settingsDialog.open) settingsDialog.showModal();
  if (!isSettingsOpen && settingsDialog.open) settingsDialog.close();
  syncUi();
});
window.addEventListener('pancake:scorechange', (event: Event) => {
  const { score, pulse } = (event as CustomEvent<{ score: number; pulse: boolean }>).detail;
  if (scoreValue) scoreValue.textContent = String(score);
  if (scoreHud && pulse) {
    scoreHud.classList.remove('score-hud-pop');
    void scoreHud.offsetWidth;
    scoreHud.classList.add('score-hud-pop');
  }
});
window.addEventListener('pancake:tutorialpopup', (event: Event) => {
  const { isOpen, text } = (event as CustomEvent<{ isOpen: boolean; text: string }>).detail;
  if (tutorialText) tutorialText.textContent = text;
  if (isOpen && !tutorialDialog.open) tutorialDialog.showModal();
  if (!isOpen && tutorialDialog.open) tutorialDialog.close();
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && !settingsDialog.open && !tutorialDialog.open && currentState !== 'menu') {
    runWhenSceneReady((scene) => scene.returnToMainMenuFromUi());
  }
});
