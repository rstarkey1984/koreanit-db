package com.example.demo.controller;

import org.springframework.security.crypto.bcrypt.BCrypt;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;

@RestController
public class HelloController {

  @GetMapping("/")
  public String hello() {

    // bcrypt 해시 생성 (salt 포함)
    String hash = BCrypt.hashpw("password", BCrypt.gensalt());

    return "hello : " + hash;
  }
}