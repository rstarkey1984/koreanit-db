# 사전 준비 사항
- git
- mysql
- docker

# 소스코드 github에서 다운받기

이 저장소는 **강의안(md) + 소스코드**가 함께 들어 있다.
WSL 환경에서는 **소스코드(`web-docker`)만** 받기 위해
Git의 **sparse-checkout** 기능을 사용한다.

```bash
git clone --filter=blob:none --no-checkout https://github.com/rstarkey1984/koreanit-db.git && cd koreanit-db && git sparse-checkout init --cone && git sparse-checkout set web-docker && git checkout HEAD -- web-docker
```
```bash
rm -rf .git
```

## 프로젝트 디렉터리

```
web-docker 
  demo -- 스프링부트 api 서버
  nginx -- nginx 설정파일
  var/www -- nginx document root 폴더 ( html,css,js,php 등 )
```


## 컨테이너로 전달할 환경변수 설정

### `web-docker/demo/.env` 파일 수정

# PHP-FPM 이미지 빌드
```
cd web-docker
```
```
docker build -t custom-php-fpm:8.3-alpine .
```

---

# 스프링부트 API 이미지 테스트 및 빌드

```
cd demo
```
## gradlew 실행권한 +
```
chmod +x gradlew
```
### 단독 실행 테스트
```
docker run --rm -p 9092:9092 --env-file ../.env --add-host host.docker.internal:host-gateway web-docker-api:1.0
```

### Docker 이미지 빌드:
```bash
docker build -t web-docker-api:1.0 .
```

빌드 성공시:
```
docker images | grep web-docker-api
```

---
# Docker Compose 설정을 기반으로 컨테이너 관리

Docker Compose 실행:
```
docker compose up -d
```

Docker Compose 중지:
```
docker compose down
```

NGINX 로그확인:
```
docker logs -f web-nginx
```

PHP-FPM 로그확인:
```
docker logs -f web-php
```