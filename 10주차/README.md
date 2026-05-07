# gian_media 패키지 - MediaPipe 손 인식 ROS2 노드

- **OS**: Ubuntu 24.04 (Noble Numbat) / VMware Workstation 17 Player
- **ROS2 배포판**: Jazzy Jalisco
- **MediaPipe**: 0.10.35 (Tasks API)
- **작성일**: 2026-05-07

---

## 목차

1. [개요](#1-개요)
2. [시스템 구성](#2-시스템-구성)
3. [사전 준비 및 의존성 설치](#3-사전-준비-및-의존성-설치)
4. [패키지 생성 및 파일 구조](#4-패키지-생성-및-파일-구조)
5. [노드 코드 설명](#5-노드-코드-설명)
6. [빌드 및 실행](#6-빌드-및-실행)
7. [실행 결과](#7-실행-결과)
8. [발행 토픽](#8-발행-토픽)
9. [트러블슈팅](#9-트러블슈팅)

---

## 1. 개요

`gian_media` 패키지는 USB 카메라(`/dev/video0`)로부터 영상을 받아 **MediaPipe Hand Landmarker**로 손을 검출하고, 검출 결과를 ROS2 토픽으로 발행하는 노드입니다.

```
USB 카메라 (/dev/video0)
        ↓  OpenCV VideoCapture
  프레임 캡처 (30fps)
        ↓  mediapipe.tasks.vision.HandLandmarker
  손 랜드마크 검출 (21개 관절)
        ↓
  ┌─────────────────────────────────────────┐
  │  ROS2 토픽 발행                          │
  │  /camera/image_raw        (원본 영상)    │
  │  /hand_detection/image    (주석 영상)    │
  │  /hand_detection/landmarks (랜드마크)   │
  │  /hand_detection/detected  (감지 여부)  │
  │  /hand_detection/hand_count (손 개수)   │
  └─────────────────────────────────────────┘
        ↓
  cv2.imshow() 로 실시간 화면 표시
```

### MediaPipe API 버전

| 버전 | API 스타일 | 비고 |
|------|-----------|------|
| 0.9.x 이하 | `mp.solutions.hands` | Legacy API (사용 불가) |
| **0.10.x 이상** | `mediapipe.tasks.python.vision` | **현재 사용** |

0.10.x부터 `mp.solutions` 속성이 완전히 제거되었으므로 반드시 Tasks API를 사용해야 합니다.

---

## 2. 시스템 구성

| 항목 | 내용 |
|------|------|
| 가상화 플랫폼 | VMware Workstation 17 Player |
| 게스트 OS | Ubuntu 24.04 LTS |
| USB 컨트롤러 | USB 3.1 (중요: 3.1 이상 필요) |
| 카메라 | USB 2.0 웹캠 |
| ROS2 | Jazzy Jalisco |
| Python | 3.12 |
| MediaPipe | 0.10.35 |
| NumPy | 1.26.4 |
| OpenCV | 4.13.0 |

---

## 3. 사전 준비 및 의존성 설치

### 3-1. MediaPipe 설치

```bash
pip install mediapipe --break-system-packages
```

> Ubuntu 24.04는 PEP 668에 의해 시스템 Python에 pip 설치 시 `--break-system-packages` 옵션이 필요합니다.

### 3-2. NumPy 버전 고정

ROS2 Jazzy의 `cv_bridge`가 NumPy 1.x로 컴파일되어 있어 NumPy 2.x와 충돌합니다. 따라서 NumPy 1.26.4로 고정합니다.

```bash
pip install "numpy==1.26.4" --break-system-packages
```

### 3-3. matplotlib pip 버전으로 교체

시스템 matplotlib이 NumPy 1.x로 컴파일되어 충돌이 발생합니다. pip 버전으로 교체합니다.

```bash
pip install matplotlib --break-system-packages
```

### 3-4. Hand Landmarker 모델 파일 다운로드

MediaPipe 0.10.x Tasks API는 외부 모델 파일(`.task`)이 필요합니다.

```bash
mkdir -p ~/.mediapipe_models
wget -O ~/.mediapipe_models/hand_landmarker.task \
  https://storage.googleapis.com/mediapipe-models/hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task
```

확인:
```bash
ls -lh ~/.mediapipe_models/hand_landmarker.task
# 출력: -rw-rw-r-- 1 gian gian 7.5M ... hand_landmarker.task
```

### 3-5. 설치 확인

```bash
python3 -c "
import numpy as np
import mediapipe as mp
import cv2
print(f'numpy:     {np.__version__}')
print(f'mediapipe: {mp.__version__}')
print(f'opencv:    {cv2.__version__}')
"
```

정상 출력:
```
numpy:     1.26.4
mediapipe: 0.10.35
opencv:    4.13.0
```

### 3-6. VMware USB 컨트롤러 설정 (중요)

USB 카메라의 YUYV 포맷이 VMware에서 정상 작동하려면 **USB 3.1** 이상이 필요합니다.

```
Virtual Machine Settings → USB Controller → USB compatibility → USB 3.1
```

---

## 4. 패키지 생성 및 파일 구조

### 4-1. 워크스페이스 및 패키지 생성

```bash
mkdir -p ~/ros2_ws/src
mkdir -p ~/ros2_ws/src/gian_media/gian_media
mkdir -p ~/ros2_ws/src/gian_media/resource
touch ~/ros2_ws/src/gian_media/resource/gian_media
touch ~/ros2_ws/src/gian_media/gian_media/__init__.py
```

### 4-2. 전체 파일 구조

```
~/ros2_ws/src/gian_media/
├── gian_media/
│   ├── __init__.py
│   └── hand_detection_node.py   ← 메인 노드 코드
├── resource/
│   └── gian_media
├── package.xml                   ← 패키지 메타정보 및 ROS 의존성
├── setup.cfg
└── setup.py                      ← 빌드 설정 및 실행 명령 등록

모델 파일 위치 (패키지 외부):
~/.mediapipe_models/
└── hand_landmarker.task          ← MediaPipe 모델 파일 (7.5MB)
```

### 4-3. package.xml

```xml
<?xml version="1.0"?>
<package format="3">
  <name>gian_media</name>
  <version>0.0.0</version>
  <description>Hand detection using MediaPipe</description>
  <maintainer email="gian@todo.todo">gian</maintainer>
  <license>Apache-2.0</license>
  <depend>rclpy</depend>
  <depend>sensor_msgs</depend>
  <depend>std_msgs</depend>
  <depend>cv_bridge</depend>
  <export>
    <build_type>ament_python</build_type>
  </export>
</package>
```

### 4-4. setup.py

```python
from setuptools import find_packages, setup

package_name = 'gian_media'

setup(
    name=package_name,
    version='0.0.0',
    packages=find_packages(exclude=['test']),
    data_files=[
        ('share/ament_index/resource_index/packages',
            ['resource/' + package_name]),
        ('share/' + package_name, ['package.xml']),
    ],
    install_requires=['setuptools'],
    zip_safe=True,
    entry_points={
        'console_scripts': [
            'hand_detection_node = gian_media.hand_detection_node:main',
        ],
    },
)
```

---

## 5. 노드 코드 설명

### 5-1. cv_bridge 우회

ROS2 Jazzy의 `cv_bridge`가 NumPy 버전 충돌을 일으키므로, OpenCV 이미지를 ROS2 메시지로 직접 변환하는 함수를 구현했습니다.

```python
def cv2_to_imgmsg(frame, node):
    msg = Image()
    msg.header.stamp = node.get_clock().now().to_msg()
    msg.height, msg.width = frame.shape[:2]
    msg.encoding = 'bgr8'
    msg.is_bigendian = 0
    msg.step = msg.width * 3
    msg.data = frame.tobytes()
    return msg
```

### 5-2. MediaPipe HandLandmarker 설정

```python
options = HandLandmarkerOptions(
    base_options=BaseOptions(model_asset_path=model_path),
    running_mode=RunningMode.VIDEO,   # 연속 프레임 처리 모드
    num_hands=max_hands,
    min_hand_detection_confidence=det_conf,
    min_tracking_confidence=trk_conf,
)
self.landmarker = HandLandmarker.create_from_options(options)
```

`RunningMode`는 3가지가 있습니다:

| RunningMode | 용도 |
|-------------|------|
| `IMAGE` | 단일 이미지 처리 |
| `VIDEO` | 연속 프레임 처리 (타임스탬프 필요) |
| `LIVE_STREAM` | 비동기 스트림 처리 (콜백 필요) |

### 5-3. 카메라 워밍업

VMware 환경에서 초기 프레임이 깨지는 문제를 방지하기 위해 30프레임을 버립니다.

```python
for _ in range(30):
    self.cap.read()
```

### 5-4. 손 랜드마크 21개 관절 구조

```
              8   12  16  20
              |   |   |   |
              7   11  15  19
              |   |   |   |
              6   10  14  18
              |   |   |   |
        4     5   9   13  17
        |     |
        3     |
        |   (손바닥)
        2
        |
        1
        |
        0 (손목)
```

| ID | 위치 |
|----|------|
| 0 | 손목 |
| 1~4 | 엄지 |
| 5~8 | 검지 |
| 9~12 | 중지 |
| 13~16 | 약지 |
| 17~20 | 소지 |

---

## 6. 빌드 및 실행

### 6-1. 빌드

```bash
cd ~/ros2_ws
source /opt/ros/jazzy/setup.bash
colcon build --packages-select gian_media --symlink-install
```

빌드 성공 출력:
```
Starting >>> gian_media
Finished <<< gian_media [1.61s]
Summary: 1 package finished [1.77s]
```

> `--symlink-install` 옵션: Python 파일 수정 후 재빌드 없이 바로 반영됩니다.

### 6-2. 워크스페이스 환경 로드

```bash
source ~/ros2_ws/install/setup.bash
```

`.bashrc`에 추가하면 터미널 시작 시 자동 적용:

```bash
echo "source /opt/ros/jazzy/setup.bash" >> ~/.bashrc
echo "source ~/ros2_ws/install/setup.bash" >> ~/.bashrc
source ~/.bashrc
```

### 6-3. 노드 실행

```bash
ros2 run gian_media hand_detection_node
```

### 6-4. 파라미터 변경 실행

```bash
ros2 run gian_media hand_detection_node --ros-args \
  -p camera_index:=0 \
  -p max_num_hands:=2 \
  -p min_detection_confidence:=0.7 \
  -p min_tracking_confidence:=0.5
```

---

## 7. 실행 결과

정상 실행 시 터미널 출력:
```
[INFO] [hand_detection_node]: HandDetectionNode started
[INFO] [hand_detection_node]: Camera: /dev/video0 | Model: /home/gian/.mediapipe_models/hand_landmarker.task
[INFO] [hand_detection_node]: Running... frame=90
[INFO] [hand_detection_node]: Hand detected: Right (conf=0.98)
```

화면 표시:

| 상태 | 화면 |
|------|------|
| 손 없음 | 상단 `No hand detected` (빨간 글씨) |
| 손 감지 | 상단 `Hands: 1` (초록 글씨) + 랜드마크 선/점 |
| 종료 | 영상 창에서 `q` 키 또는 `Ctrl+C` |

토픽 확인 (별도 터미널):

```bash
source ~/ros2_ws/install/setup.bash

# 활성 토픽 목록
ros2 topic list

# 손 감지 여부 실시간 확인
ros2 topic echo /hand_detection/detected

# 손 개수 확인
ros2 topic echo /hand_detection/hand_count

# 랜드마크 좌표 확인
ros2 topic echo /hand_detection/landmarks

# 발행 주기 확인 (30Hz 근처여야 정상)
ros2 topic hz /camera/image_raw
```

---

## 8. 발행 토픽

| 토픽 이름 | 메시지 타입 | 내용 |
|-----------|-----------|------|
| `/camera/image_raw` | `sensor_msgs/Image` | USB 카메라 원본 영상 (bgr8) |
| `/hand_detection/image` | `sensor_msgs/Image` | 랜드마크 그려진 주석 영상 (bgr8) |
| `/hand_detection/landmarks` | `std_msgs/String` | 손 관절 21개 좌표 JSON |
| `/hand_detection/detected` | `std_msgs/Bool` | 손 감지 여부 (true/false) |
| `/hand_detection/hand_count` | `std_msgs/Int32` | 감지된 손 개수 (0~2) |

### landmarks JSON 형식

```json
[
  {
    "hand_index": 0,
    "handedness": "Right",
    "confidence": 0.9823,
    "landmarks": [
      {"id": 0,  "x": 0.512, "y": 0.743, "z": 0.001},
      {"id": 1,  "x": 0.498, "y": 0.681, "z": -0.023},
      ...
      {"id": 20, "x": 0.390, "y": 0.520, "z": -0.089}
    ]
  }
]
```

- `x`, `y`: 0.0 ~ 1.0 정규화 좌표 (이미지 너비/높이 기준)
- `z`: 상대 깊이 (손목 기준, 음수 = 카메라 방향)
- 손이 없을 때: 빈 배열 `[]`

---

## 9. 트러블슈팅

### NumPy 버전 충돌

**증상:**
```
A module that was compiled using NumPy 1.x cannot be run in NumPy 2.x
```

**원인:** mediapipe 설치 시 NumPy 2.x가 함께 설치되지만 ROS2 Jazzy cv_bridge가 NumPy 1.x 기준으로 컴파일되어 있음

**해결:**
```bash
pip install "numpy==1.26.4" --break-system-packages
pip install matplotlib --break-system-packages
```

### matplotlib 충돌

**증상:**
```
ImportError: numpy.core.multiarray failed to import
```

**원인:** 시스템 matplotlib이 NumPy 1.x로 컴파일되어 있음

**해결:**
```bash
pip install matplotlib --break-system-packages --force-reinstall
```

### VMware USB 카메라 초록색 화면

**증상:** OpenCV 창에 초록색 또는 줄무늬 화면 표시

**원인:** VMware USB 2.0 컨트롤러에서 YUYV 포맷 데이터가 손상됨

**해결:** VMware USB 컨트롤러를 USB 3.1로 변경
```
Virtual Machine Settings → USB Controller → USB 3.1
```

### `AttributeError: module 'mediapipe' has no attribute 'solutions'`

**원인:** mediapipe 0.10.x에서 legacy API 제거됨

**해결:** Tasks API로 변경
```python
# ❌ 구버전 (0.9.x 이하)
mp_hands = mp.solutions.hands

# ✅ 신버전 (0.10.x 이상)
from mediapipe.tasks.python.vision import HandLandmarker, HandLandmarkerOptions
```

### `FileNotFoundError: hand_landmarker.task`

**해결:**
```bash
mkdir -p ~/.mediapipe_models
wget -O ~/.mediapipe_models/hand_landmarker.task \
  https://storage.googleapis.com/mediapipe-models/hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task
```

### `Cannot open /dev/video0`

**해결:**
```bash
# 카메라 장치 목록 확인
ls /dev/video*

# VMware에서 카메라 연결
Player → Removable Devices → [카메라] → Connect

# 다른 번호로 실행
ros2 run gian_media hand_detection_node --ros-args -p camera_index:=2
```

### SSH 환경에서 `qt.qpa.xcb: could not connect to display`

**원인:** SSH 연결 시 DISPLAY 환경변수 없음

**해결:** VMware Ubuntu 화면에서 직접 터미널을 열어 실행
