# ----------------------------------------
# 1단계: 빌드 스테이지
# ----------------------------------------
FROM eclipse-temurin:21-jdk AS builder

WORKDIR /app

# Gradle 관련 파일 복사
COPY gradlew .
COPY gradle gradle
COPY build.gradle settings.gradle ./
COPY src src

# 실행 가능한 Spring Boot JAR 생성
RUN ./gradlew clean bootJar -x test


# ----------------------------------------
# 2단계: 실행 스테이지
# ----------------------------------------
FROM eclipse-temurin:21-jre

WORKDIR /app

# 빌드 결과물 복사
COPY --from=builder /app/build/libs/*.jar app.jar

EXPOSE 9092

# 컨테이너 시작 시 Spring Boot 실행
ENTRYPOINT ["java", "-jar", "app.jar"]
