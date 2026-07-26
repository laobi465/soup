package com.cardauth.sdk.model;

public class CardVerifyResult {
    private int cardId;
    private int cardType;
    private String cardTypeText;
    private int status;
    private String statusText;
    private String expireTime;
    private int remainingDuration;
    private int bindDeviceCount;
    private int bindLimit;
    private boolean isPermanent;
    private boolean isSoftExpired;

    public int getCardId() {
        return cardId;
    }

    public void setCardId(int cardId) {
        this.cardId = cardId;
    }

    public int getCardType() {
        return cardType;
    }

    public void setCardType(int cardType) {
        this.cardType = cardType;
    }

    public String getCardTypeText() {
        return cardTypeText;
    }

    public void setCardTypeText(String cardTypeText) {
        this.cardTypeText = cardTypeText;
    }

    public int getStatus() {
        return status;
    }

    public void setStatus(int status) {
        this.status = status;
    }

    public String getStatusText() {
        return statusText;
    }

    public void setStatusText(String statusText) {
        this.statusText = statusText;
    }

    public String getExpireTime() {
        return expireTime;
    }

    public void setExpireTime(String expireTime) {
        this.expireTime = expireTime;
    }

    public int getRemainingDuration() {
        return remainingDuration;
    }

    public void setRemainingDuration(int remainingDuration) {
        this.remainingDuration = remainingDuration;
    }

    public int getBindDeviceCount() {
        return bindDeviceCount;
    }

    public void setBindDeviceCount(int bindDeviceCount) {
        this.bindDeviceCount = bindDeviceCount;
    }

    public int getBindLimit() {
        return bindLimit;
    }

    public void setBindLimit(int bindLimit) {
        this.bindLimit = bindLimit;
    }

    public boolean isPermanent() {
        return isPermanent;
    }

    public void setPermanent(boolean permanent) {
        isPermanent = permanent;
    }

    public boolean isSoftExpired() {
        return isSoftExpired;
    }

    public void setSoftExpired(boolean softExpired) {
        isSoftExpired = softExpired;
    }
}
