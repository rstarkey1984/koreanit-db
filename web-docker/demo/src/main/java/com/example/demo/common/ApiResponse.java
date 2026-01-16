package com.example.demo.common;

public class ApiResponse<T> {
  public boolean ok;
  public T data;
  public String message;

  private ApiResponse(boolean ok, T data, String message) {
    this.ok = ok;
    this.data = data;
    this.message = message;
  }

  public static <T> ApiResponse<T> ok(T data) {
    return new ApiResponse<>(true, data, null);
  }

  public static <T> ApiResponse<T> fail(String message) {
    return new ApiResponse<>(false, null, message);
  }
}