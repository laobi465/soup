package com.cardauth.inject.model;

import com.fasterxml.jackson.annotation.JsonInclude;
import com.fasterxml.jackson.annotation.JsonProperty;

import java.util.ArrayList;
import java.util.List;

/**
 * 注入响应体。
 *
 * <p>JSON 字段使用 snake_case，与 PHP 端读取的 {@code success / output_path / error} 对齐；
 * {@code steps} 为额外诊断字段（PHP 端当前忽略，可用于排查）。
 */
@JsonInclude(JsonInclude.Include.NON_NULL)
public class InjectResponse {

    private boolean success;

    @JsonProperty("output_path")
    private String outputPath;

    private String error;

    private List<String> steps = new ArrayList<>();

    public boolean isSuccess() {
        return success;
    }

    public void setSuccess(boolean success) {
        this.success = success;
    }

    public String getOutputPath() {
        return outputPath;
    }

    public void setOutputPath(String outputPath) {
        this.outputPath = outputPath;
    }

    public String getError() {
        return error;
    }

    public void setError(String error) {
        this.error = error;
    }

    public List<String> getSteps() {
        return steps;
    }

    public void setSteps(List<String> steps) {
        this.steps = steps;
    }
}
