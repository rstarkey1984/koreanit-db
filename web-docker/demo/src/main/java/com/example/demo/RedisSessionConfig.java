package com.example.demo;

import jakarta.annotation.PostConstruct;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.context.annotation.Configuration;
import org.springframework.context.annotation.Profile;
import org.springframework.session.data.redis.config.annotation.web.http.EnableRedisHttpSession;

@Profile("redis-session")
@Configuration
@EnableRedisHttpSession
public class RedisSessionConfig {

  private static final Logger log = LoggerFactory.getLogger(RedisSessionConfig.class);

  @PostConstruct
  public void init() {
    log.info("Redis HTTP Session ENABLED (profile=redis-session)");
    System.out.println("### Redis HTTP Session ENABLED ###");
  }
}
