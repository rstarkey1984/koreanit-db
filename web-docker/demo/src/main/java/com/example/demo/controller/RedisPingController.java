package com.example.demo.controller;

import org.springframework.data.redis.connection.RedisConnectionFactory;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;

@RestController
public class RedisPingController {

  private final RedisConnectionFactory factory;

  public RedisPingController(RedisConnectionFactory factory) {
    this.factory = factory;
  }

  @GetMapping("/redis-ping")
  public String ping() {
    var conn = factory.getConnection();
    try {
      return conn.ping(); // "PONG" 기대
    } finally {
      conn.close();
    }
  }
}