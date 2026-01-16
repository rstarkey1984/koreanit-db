// package com.example.demo.config;

// import org.springframework.context.annotation.Configuration;
// import org.springframework.web.servlet.config.annotation.CorsRegistry;
// import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;

// @Configuration
// public class WebConfig implements WebMvcConfigurer {

// @Override
// public void addCorsMappings(CorsRegistry registry) {
// registry.addMapping("/**")
// // 프런트 주소(정확히)
// .allowedOrigins("http://test.localhost")
// // 필요한 메서드
// .allowedMethods("GET", "POST", "PUT", "DELETE", "OPTIONS")
// // 보낼 헤더
// .allowedHeaders("*")
// // 쿠키(세션) 허용
// .allowCredentials(true)
// // preflight 캐시(선택)
// .maxAge(3600);
// }
// }