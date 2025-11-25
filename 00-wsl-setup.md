# 개발환경 구축 (WSL → Ubuntu → SSH → Oracle → DBeaver)

## 📘 학습 개요

이 문서는 **Windows → WSL2 → Ubuntu 24.04 → SSH 서버 → Docker → Oracle DB → DBeaver** 까지
"개발환경을 하나의 통합된 서버처럼" 구축하는 전 과정을 다룹니다.

WSL2는 Windows 위에서 실제 Linux 커널을 사용하는 가상머신 기반 환경이며,
Docker와 Oracle Database도 문제 없이 실행할 수 있습니다.

## 💡 주요 내용
- WSL2 ( Windows Subsystem Linux ) 설치
- Ubuntu 24.04 초기 설정  
- SSH 서버 설정  
- Oracle Database 설치  
- DBeaver 연결  

---

# 1️⃣ WSL2 ( Windows Subsystem Linux ) 설치
> **WSL**은 Windows에서 Linux를 실행하는 기술입니다

## 1-1. PowerShell 관리자 실행
Windows 검색 → **PowerShell → 우클릭 → 관리자 권한으로 실행**

## 1-2. WSL 및 가상화 기능 활성화
```powershell
dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart
dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
```

## 1-3. WSL2를 기본 버전으로 설정
```powershell
wsl --set-default-version 2
```

## 1-4. Ubuntu 24.04 설치
```powershell
wsl --install -d Ubuntu-24.04
```

설치 후 자동으로 Ubuntu 터미널이 실행됨.

---

# 2️⃣ Ubuntu 24.04 초기 설정

## 2-1. 계정 생성  
Ubuntu가 처음 실행되면 사용자 이름/비밀번호를 입력합니다.  
- 사용자명: 소문자만 허용  
- 비밀번호 입력 시 화면에 출력되지 않지만 정상 입력됨

---

## 2-2. /etc/wsl.conf 설정 (중요)
Windows 시스템 경로 언마운트 + systemd 활성화 + 환경 분리

```bash
sudo sh -c 'cat > /etc/wsl.conf << "EOF"
[boot]
systemd=true

[user]
default=ubuntu

[automount]
enabled=false

[interop]
enabled=false
appendWindowsPath=false

[network]
hostname=ubuntu24
EOF'
```

확인:
```bash
cat /etc/wsl.conf
```

---

## 2-3. WSL 재시작
```powershell
wsl --shutdown
wsl
```

정상이면:
```
ubuntu@ubuntu24:~$
```

---

## 2-4. 시스템 업데이트
```bash
sudo apt update && sudo apt upgrade -y
```

---

# 3️⃣ OpenSSH Server 설치 및 설정

## 3-1. SSH 서버 설치
```bash
sudo apt install -y openssh-server
```

상태 확인:
```bash
sudo systemctl status ssh
```

---

## 3-2. SSH 포트 변경 (22 → 2222)
```bash
sudo nano /etc/ssh/sshd_config
```

수정:
```
#Port 22
Port 2222
```

재시작:
```bash
sudo systemctl stop ssh
sudo systemctl stop ssh.socket
sudo systemctl restart ssh
```

---

## 3-3. Windows에서 SSH 접속 테스트
```powershell
ssh ubuntu@localhost -p 2222
```

키 충돌 시:
```powershell
ssh-keygen -R localhost
```

---

# 4️⃣ SSH 키 기반 접속 설정

## 4-1. Windows에서 SSH 키 생성
```powershell
mkdir ~/.ssh
cd ~/.ssh
ssh-keygen -C "ubuntu24" -f "$env:USERPROFILE\.ssh\myubuntu24key"
```

## 4-2. Ubuntu에 공개키 등록
```bash
mkdir -p ~/.ssh
touch ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
nano ~/.ssh/authorized_keys
```

.pub 파일 내용 복사 후 저장

## 4-3. SSH 키 접속
```powershell
ssh -i "$env:USERPROFILE\.ssh\myubuntu24key" ubuntu@localhost -p 2222
```

---

# 5️⃣ Windows SSH config 등록

파일 경로:
```
C:\\Users\\사용자\\.ssh\\config
```

내용:
```
Host ubuntu24
    HostName localhost
    Port 2222
    IdentityFile C:\\Users\\사용자\\.ssh\\myubuntu24key
    User ubuntu
```

접속:
```powershell
ssh ubuntu24
```

---

# 6️⃣ Oracle Database (Docker 기반)

## 6-1. 필수 패키지 설치
```bash
sudo apt install -y ca-certificates curl gnupg lsb-release
```

## 6-2. Docker GPG 키 등록
```bash
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg
```

## 6-3. Docker 저장소 등록
```
echo \
  "deb [arch=$(dpkg --print-architecture) \
  signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
```

## 6-4. Docker 설치
```
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

## 6-5. Docker 그룹 등록
```bash
sudo usermod -aG docker $USER
```

---

## 6-6. WSL 재시작
```bash
exit
```
```powershell
wsl --shutdown
wsl
```

---

## 6-7. Docker 정상 확인
```bash
docker version
docker ps
```

---

## 6-8. Oracle XE 이미지 다운로드
```bash
docker pull gvenzl/oracle-xe:21-slim
```

## 6-9. Docker Compose 생성
```yaml
services:
  oracle-xe:
    image: gvenzl/oracle-xe:21-slim
    container_name: oracle-xe
    ports:
      - "1521:1521"
    environment:
      ORACLE_PASSWORD: Oracle1234
    volumes:
      - oracle-xe-data:/opt/oracle/oradata
    restart: unless-stopped

volumes:
  oracle-xe-data:
```

## 6-10. 실행
```bash
docker compose up -d
```

## 6-11. 정상 여부 체크
```bash
docker compose logs -f oracle-xe
```

## 6-12. Oracle 접속
```bash
docker compose exec oracle-xe bash
sqlplus sys/Oracle1234@localhost:1521/XEPDB1 as sysdba
```

## 6-13. 실습 계정 생성
```sql
CREATE USER test IDENTIFIED BY test1234 DEFAULT TABLESPACE USERS TEMPORARY TABLESPACE TEMP QUOTA UNLIMITED ON USERS;
GRANT CONNECT, RESOURCE TO test;
```

---

# 7️⃣ DBeaver 접속 정보
| 항목 | 값 |
|------|------|
| Host | localhost |
| Port | 1521 |
| Database | XEPDB1 |
| Username | test |
| Password | test1234 |
| Role | Normal |

---

# 📌 개발환경 전체 구조

```
[ Windows ]
    ├─ PowerShell → WSL 제어
[ WSL2 ]
    ├─ Ubuntu 24.04
    │    ├─ SSH 서버
    │    ├─ Docker
    │    │    └─ Oracle DB 컨테이너
[ DBeaver ] → localhost:1521 연결
```

---

# 💡 요약정리

- Windows에 WSL2 + Ubuntu를 설치해 Linux 개발환경 구축  
- /etc/wsl.conf 로 systemd 활성화  
- SSH 서버로 Windows ↔ Ubuntu 연결  
- Docker 기반 Oracle DB 컨테이너 설치  
- DBeaver로 Oracle DB 접속 후 실습  

---

# 🧩 실습 / 과제
- Windows → Ubuntu SSH 접속하기  
- `/etc/wsl.conf` 파일 확인  
- Oracle 컨테이너 자동 재시작 확인  
- DBeaver로 test 계정 접속하기  

---

# 참고) WSL 명령어

```powershell
wsl -l -v
wsl
wsl --shutdown
wsl --terminate Ubuntu-24.04
wsl -e free -h
```

# 참고) Docker 명령어

```bash
docker images
docker ps
docker compose up -d
docker compose down
docker compose logs -f oracle-xe
docker compose exec oracle-xe bash
```
