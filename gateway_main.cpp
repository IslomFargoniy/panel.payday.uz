#include <iostream>
#include <string>
#include <vector>
#include <map>
#include <mutex>
#include <thread>
#include <chrono>
#include <cstring>
#include <sstream>
#include <fstream>
#include <sys/stat.h>
#include <sys/types.h>

#include "/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/include/HCISUPCMS.h"
#include "/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/include/HCISUPAlarm.h"
#include "/opt/hikvision-gateway/include/httplib.h"
#include "/opt/hikvision-gateway/include/json.hpp"

using json = nlohmann::json;

struct GatewayConfig {
    std::string server_ip = "193.180.213.188";
    int server_port = 80;
    std::string server_host = "panel.payday.uz";
    std::string storage_base_dir = "/var/www/panel_payday_usr/data/www/panel.payday.uz/storage/app/public/hikvision";
    int cms_port = 7660;
    int alarm_port = 7200;
    int api_port = 7661;
    std::string das_address = "193.180.213.188";
    int das_port = 7660;
};

static GatewayConfig g_config;

void loadConfig(const std::string& configPath) {
    std::ifstream f(configPath);
    if (!f.is_open()) {
        std::cout << "[CONFIG] Config file not found at " << configPath << ", using default values." << std::endl;
        return;
    }
    try {
        json j;
        f >> j;
        if (j.contains("server_ip")) g_config.server_ip = j["server_ip"].get<std::string>();
        if (j.contains("server_port")) g_config.server_port = j["server_port"].get<int>();
        if (j.contains("server_host")) g_config.server_host = j["server_host"].get<std::string>();
        if (j.contains("storage_base_dir")) g_config.storage_base_dir = j["storage_base_dir"].get<std::string>();
        if (j.contains("cms_port")) g_config.cms_port = j["cms_port"].get<int>();
        if (j.contains("alarm_port")) g_config.alarm_port = j["alarm_port"].get<int>();
        if (j.contains("api_port")) g_config.api_port = j["api_port"].get<int>();
        if (j.contains("das_address")) g_config.das_address = j["das_address"].get<std::string>();
        if (j.contains("das_port")) g_config.das_port = j["das_port"].get<int>();
        std::cout << "[CONFIG] Loaded successfully from " << configPath << std::endl;
    } catch (const std::exception& e) {
        std::cerr << "[CONFIG ERROR] " << e.what() << ", using defaults." << std::endl;
    }
}

struct ConnectedDevice {
    LONG login_id;
    std::string device_id;
    std::string ip;
    std::string serial;
    std::string protocol_ver;
    bool online;
    std::string last_seen;
};

static std::map<std::string, ConnectedDevice> g_devices;
static std::map<LONG, std::string> g_login_to_device;
static std::map<std::string, std::string> g_device_keys;
static std::mutex g_device_mutex;

std::string getCurrentTimeString() {
    auto now = std::chrono::system_clock::now();
    std::time_t now_c = std::chrono::system_clock::to_time_t(now);
    char buf[64];
    std::strftime(buf, sizeof(buf), "%Y-%m-%d %H:%M:%S", std::localtime(&now_c));
    return std::string(buf);
}

void notifyLaravelDeviceStatus(const std::string& device_id, const std::string& status, const std::string& ip, const std::string& serial) {
    std::thread([device_id, status, ip, serial]() {
        try {
            httplib::Client cli(g_config.server_ip.c_str(), g_config.server_port);
            cli.set_connection_timeout(std::chrono::seconds(3));
            cli.set_read_timeout(std::chrono::seconds(3));
            httplib::Headers headers = {{"Host", g_config.server_host}};
            json payload = {
                {"device_id", device_id},
                {"status", status},
                {"ip", ip},
                {"serial", serial}
            };
            cli.Post("/api/hikvision-device-status", headers, payload.dump(), "application/json");
        } catch (...) {}
    }).detach();
}

std::string fetchDeviceKey(const std::string& device_id) {
    {
        std::lock_guard<std::mutex> lock(g_device_mutex);
        auto it = g_device_keys.find(device_id);
        if (it != g_device_keys.end() && !it->second.empty()) {
            return it->second;
        }
    }
    
    try {
        httplib::Client cli(g_config.server_ip.c_str(), g_config.server_port);
        cli.set_connection_timeout(std::chrono::seconds(2));
        cli.set_read_timeout(std::chrono::seconds(2));
        httplib::Headers headers = {{"Host", g_config.server_host}};
        auto res = cli.Get(("/api/hikvision-device-key?device_id=" + device_id).c_str(), headers);
        if (res && res->status == 200) {
            auto j = json::parse(res->body);
            if (j.contains("encryption_key")) {
                std::string key = j["encryption_key"];
                std::lock_guard<std::mutex> lock(g_device_mutex);
                g_device_keys[device_id] = key;
                return key;
            }
        }
    } catch (...) {}

    return "PayDay14!2026";
}

void forwardAlarmToLaravel(const std::string& serial, DWORD alarmType, const std::string& rawPayload, const std::string& savedPicFilename) {
    std::thread([serial, alarmType, rawPayload, savedPicFilename]() {
        try {
            httplib::Client cli(g_config.server_ip.c_str(), g_config.server_port);
            cli.set_connection_timeout(std::chrono::seconds(4));
            cli.set_read_timeout(std::chrono::seconds(4));
            httplib::Headers headers = {{"Host", g_config.server_host}};

            json postObj;
            try {
                auto parsed = json::parse(rawPayload);
                postObj = parsed;
                if (!postObj.contains("shortSerialNumber") || postObj["shortSerialNumber"].empty()) {
                    postObj["shortSerialNumber"] = serial;
                }
            } catch (...) {
                postObj["AccessControllerEvent"] = {
                    {"serialNo", serial},
                    {"rawAlarm", rawPayload}
                };
                postObj["shortSerialNumber"] = serial;
            }

            if (!savedPicFilename.empty()) {
                postObj["picture"] = savedPicFilename;
            }

            cli.Post("/api/hikvision-callback", headers, postObj.dump(), "application/json");
        } catch (const std::exception& e) {
            std::cerr << "[ISUP ALARM FORWARD ERROR] " << e.what() << std::endl;
        }
    }).detach();
}

std::string savePictureBytes(const std::string& serial, const BYTE* pData, DWORD dwLen) {
    if (!pData || dwLen == 0) return "";

    std::string baseDir = g_config.storage_base_dir + "/" + serial;
    mkdir(g_config.storage_base_dir.c_str(), 0775);
    mkdir(baseDir.c_str(), 0775);

    auto now_ms = std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::system_clock::now().time_since_epoch()).count();
    std::string filename = std::to_string(now_ms) + "_isup.jpg";
    std::string fullPath = baseDir + "/" + filename;

    std::ofstream ofs(fullPath, std::ios::binary);
    if (ofs.is_open()) {
        ofs.write(reinterpret_cast<const char*>(pData), dwLen);
        ofs.close();
        chmod(fullPath.c_str(), 0664);
        std::cout << "📸 [ISUP PIC SAVED] " << fullPath << " (" << dwLen << " bytes)" << std::endl;
        return filename;
    } else {
        std::cerr << "✖ [ISUP PIC SAVE FAILED] " << fullPath << std::endl;
    }
    return "";
}

BOOL CALLBACK AlarmMsgCallBack(LONG iHandle, NET_EHOME_ALARM_MSG *pAlarmMsg, void *pUser) {
    if (!pAlarmMsg) return TRUE;

    std::string serial = pAlarmMsg->sSerialNumber;
    DWORD alarmType = pAlarmMsg->dwAlarmType;
    std::string payload = "";
    std::string savedPic = "";

    {
        std::lock_guard<std::mutex> lock(g_device_mutex);
        for (auto& pair : g_devices) {
            if (pair.second.serial == serial || pair.second.device_id == serial) {
                pair.second.last_seen = getCurrentTimeString();
                pair.second.online = true;
                break;
            }
        }
    }

    // 1. Check XML Buffer
    if (pAlarmMsg->pXmlBuf && pAlarmMsg->dwXmlBufLen > 0) {
        payload = std::string((char*)pAlarmMsg->pXmlBuf, pAlarmMsg->dwXmlBufLen);
    }

    // 2. Check ISAPI / ACS Alarm Structure
    if (pAlarmMsg->pAlarmInfo && pAlarmMsg->dwAlarmInfoLen > 0) {
        if (alarmType == EHOME_ISAPI_ALARM || alarmType == EHOME_ALARM_ACS || alarmType == EHOME_ALARM_FACESNAP_REPORT) {
            auto* pIsapiInfo = (NET_EHOME_ALARM_ISAPI_INFO*)pAlarmMsg->pAlarmInfo;
            if (pIsapiInfo->pAlarmData && pIsapiInfo->dwAlarmDataLen > 0) {
                payload = std::string(pIsapiInfo->pAlarmData, pIsapiInfo->dwAlarmDataLen);
            }

            // Extract picture if attached
            if (pIsapiInfo->byPicturesNumber > 0 && pIsapiInfo->pPicPackData) {
                auto* pPicData = (NET_EHOME_ALARM_ISAPI_PICDATA*)pIsapiInfo->pPicPackData;
                for (BYTE i = 0; i < pIsapiInfo->byPicturesNumber; i++) {
                    if (pPicData[i].dwPicLen > 0 && pPicData[i].pPicData) {
                        savedPic = savePictureBytes(serial, pPicData[i].pPicData, pPicData[i].dwPicLen);
                        if (!savedPic.empty()) break;
                    }
                }
            }
        }
    }

    std::cout << "🔔 [ISUP ALARM] Serial: " << serial << " | AlarmType: " << alarmType
              << " | PayloadLen: " << payload.length()
              << " | HasPic: " << (!savedPic.empty() ? savedPic : "none") << std::endl;

    if (!payload.empty() || !savedPic.empty()) {
        forwardAlarmToLaravel(serial, alarmType, payload, savedPic);
    }

    return TRUE;
}

BOOL CALLBACK RegistrationCallBack(LONG lUserID, DWORD dwDataType, void* pOutBuffer, DWORD dwOutLen, void* pInBuffer, DWORD dwInLen, void* pUser) {
    if (dwDataType == ENUM_DEV_AUTH) {
        auto* pDevInfo = (NET_EHOME_DEV_REG_INFO_V12*)pOutBuffer;
        if (pDevInfo) {
            std::string device_id = (char*)pDevInfo->struRegInfo.byDeviceID;
            std::string ip = pDevInfo->struRegInfo.struDevAdd.szIP;
            std::cout << "[ISUP AUTH] Device: " << device_id << " IP: " << ip << std::endl;

            std::string key = fetchDeviceKey(device_id);
            strncpy((char*)pInBuffer, key.c_str(), dwInLen - 1);
            ((char*)pInBuffer)[dwInLen - 1] = '\0';
            std::cout << "[ISUP AUTH] Sent Key for: " << device_id << " (Key: " << key << ")" << std::endl;
        }
    } else if (dwDataType == ENUM_DEV_SESSIONKEY) {
        auto* pDevInfo = (NET_EHOME_DEV_REG_INFO_V12*)pOutBuffer;
        if (pDevInfo) {
            std::string device_id = (char*)pDevInfo->struRegInfo.byDeviceID;
            std::cout << "[ISUP SESSIONKEY] Device: " << device_id << std::endl;

            NET_EHOME_DEV_SESSIONKEY struSessionkey = {0};
            memcpy(struSessionkey.sDeviceID, pDevInfo->struRegInfo.byDeviceID, MAX_DEVICE_ID_LEN);
            memcpy(struSessionkey.sSessionKey, pDevInfo->struRegInfo.bySessionKey, MAX_MASTER_KEY_LEN);
            if (!NET_ECMS_SetDeviceSessionKey(&struSessionkey)) {
                std::cout << "NET_ECMS_SetDeviceSessionKey error: " << NET_ECMS_GetLastError() << std::endl;
            }
        }
    } else if (dwDataType == ENUM_DEV_DAS_REQ) {
        json jDas;
        jDas["Type"] = "DAS";
        jDas["DasInfo"] = {
            {"Address", g_config.das_address},
            {"Domain", ""},
            {"ServerID", ""},
            {"Port", g_config.das_port},
            {"UdpPort", 0}
        };
        std::string dasInfo = jDas.dump();
        std::cout << "[ISUP DAS REQ] Sending DAS Redirect: " << dasInfo << std::endl;
        strncpy((char*)pInBuffer, dasInfo.c_str(), dwInLen - 1);
        ((char*)pInBuffer)[dwInLen - 1] = '\0';
    } else if (dwDataType == ENUM_DEV_ON) {
        auto* pDevInfo = (NET_EHOME_DEV_REG_INFO*)pOutBuffer;
        if (pDevInfo) {
            std::string device_id = (char*)pDevInfo->byDeviceID;
            std::string ip = pDevInfo->struDevAdd.szIP;
            std::string serial = (char*)pDevInfo->sDeviceSerial;
            std::string proto = (char*)pDevInfo->byDevProtocolVersion;
            std::string now_str = getCurrentTimeString();

            {
                std::lock_guard<std::mutex> lock(g_device_mutex);
                ConnectedDevice dev;
                dev.login_id = lUserID;
                dev.device_id = device_id;
                dev.ip = ip;
                dev.serial = serial;
                dev.protocol_ver = proto;
                dev.online = true;
                dev.last_seen = now_str;

                g_devices[device_id] = dev;
                g_login_to_device[lUserID] = device_id;
            }

            std::cout << "==================================================" << std::endl;
            std::cout << "🎉 [ISUP ONLINE] Device Connected!" << std::endl;
            std::cout << "   Device ID: " << device_id << std::endl;
            std::cout << "   Login ID : " << lUserID << std::endl;
            std::cout << "   IP       : " << ip << std::endl;
            std::cout << "   Serial   : " << serial << std::endl;
            std::cout << "   Protocol : " << proto << std::endl;
            std::cout << "==================================================" << std::endl;

            notifyLaravelDeviceStatus(device_id, "online", ip, serial);
        }

        // Configure Keep-alive params, Alarm Server, and Picture Server redirection
        auto* pServerInfo = (NET_EHOME_SERVER_INFO*)pInBuffer;
        if (pServerInfo) {
            pServerInfo->dwTimeOutCount = 6;
            pServerInfo->dwKeepAliveSec = 15;
            strcpy(pServerInfo->struTCPAlarmSever.szIP, "193.180.213.188");
            pServerInfo->struTCPAlarmSever.wPort = 7200;
            pServerInfo->dwAlarmServerType = 1;

            strcpy(pServerInfo->struPictureSever.szIP, "193.180.213.188");
            pServerInfo->struPictureSever.wPort = 7200;
            pServerInfo->dwPicServerType = 4; // EHome 5.0 Storage Protocol
        }
    } else if (dwDataType == ENUM_DEV_OFF) {
        std::string device_id = "unknown";
        {
            std::lock_guard<std::mutex> lock(g_device_mutex);
            auto it = g_login_to_device.find(lUserID);
            if (it != g_login_to_device.end()) {
                device_id = it->second;
                g_devices[device_id].online = false;
                g_login_to_device.erase(it);
            }
        }

        std::cout << "[ISUP OFFLINE] Device disconnected: " << device_id << " (Login ID: " << lUserID << ")" << std::endl;
        NET_ECMS_ForceLogout(lUserID);
        notifyLaravelDeviceStatus(device_id, "offline", "", "");
    }

    return TRUE;
}

int main(int argc, char* argv[]) {
    std::string configPath = "gateway_config.json";
    if (argc > 1) {
        configPath = argv[1];
    }
    loadConfig(configPath);

    std::cout << "Starting Hikvision ISUP 5.0 Gateway Server..." << std::endl;

    if (!NET_ECMS_Init()) {
        std::cerr << "Failed to initialize NET_ECMS! Error: " << NET_ECMS_GetLastError() << std::endl;
        return 1;
    }

    BOOL bSessionKeyReqMod = TRUE;
    NET_ECMS_SetSDKLocalCfg(SESSIONKEY_REQ_MOD, &bSessionKeyReqMod);

    NET_EHOME_LOCAL_ACCESS_SECURITY struAccessSecure = {0};
    struAccessSecure.dwSize = sizeof(struAccessSecure);
    struAccessSecure.byAccessSecurity = 0;
    NET_ECMS_SetSDKLocalCfg(ACTIVE_ACCESS_SECURITY, &struAccessSecure);

    NET_EHOME_SET_REREGISTER_MODE struSetReRegisterMode = {0};
    struSetReRegisterMode.dwSize = sizeof(struSetReRegisterMode);
    struSetReRegisterMode.dwReRegisterMode = 0;
    NET_ECMS_SetSDKLocalCfg(SET_REREGISTER_MODE, &struSetReRegisterMode);

    NET_EHOME_SEND_PARAM struSendParam = {0};
    struSendParam.dwSize = sizeof(struSendParam);
    struSendParam.bySendTimes = 3;
    NET_ECMS_SetSDKLocalCfg(SEND_PARAM, &struSendParam);

    NET_EHOME_REGISTER_LISTEN_MODE struRegMode = {0};
    struRegMode.dwSize = sizeof(struRegMode);
    struRegMode.dwRegisterListenMode = REGISTER_LISTEN_MODE_ALL;
    NET_ECMS_SetSDKLocalCfg(REGISTER_LISTEN_MODE, &struRegMode);

    // Start CMS Listen on 0.0.0.0:cms_port
    NET_EHOME_CMS_LISTEN_PARAM struListen = {0};
    strcpy(struListen.struAddress.szIP, "0.0.0.0");
    struListen.struAddress.wPort = (WORD)g_config.cms_port;
    struListen.fnCB = RegistrationCallBack;
    struListen.dwKeepAliveSec = 15;
    struListen.dwTimeOutCount = 6;

    LONG listenHandle = NET_ECMS_StartListen(&struListen);
    if (listenHandle < 0) {
        std::cerr << "Failed to start ISUP listening on port " << g_config.cms_port << "! Error: " << NET_ECMS_GetLastError() << std::endl;
        NET_ECMS_Fini();
        return 1;
    }

    DWORD ver = NET_ECMS_GetBuildVersion();
    std::cout << "✔ ISUP 5.0 CMS listening on 0.0.0.0:" << g_config.cms_port << " (Version: " << std::hex << ver << ")" << std::endl;

    // Start Alarm Listen on 0.0.0.0:alarm_port
    LONG alarmHandle = -1;
    if (NET_EALARM_Init()) {
        NET_EHOME_ALARM_LISTEN_PARAM struAlarmParam = {0};
        strcpy(struAlarmParam.struAddress.szIP, "0.0.0.0");
        struAlarmParam.struAddress.wPort = (WORD)g_config.alarm_port;
        struAlarmParam.fnMsgCb = AlarmMsgCallBack;
        struAlarmParam.byProtocolType = 0;
        struAlarmParam.byUseCmsPort = 0;
        struAlarmParam.byUseThreadPool = 1;
        struAlarmParam.dwKeepAliveSec = 15;
        struAlarmParam.dwTimeOutCount = 6;

        alarmHandle = NET_EALARM_StartListen(&struAlarmParam);
        if (alarmHandle >= 0) {
            std::cout << "✔ ISUP 5.0 Alarm Server listening on 0.0.0.0:" << g_config.alarm_port << std::endl;
        }
    }

    // Start HTTP REST API server on port 7661
    httplib::Server svr;

    svr.Get("/health", [](const httplib::Request&, httplib::Response& res) {
        json j;
        j["status"] = "ok";
        j["service"] = "hikvision-isup-gateway";
        {
            std::lock_guard<std::mutex> lock(g_device_mutex);
            j["connected_devices_count"] = g_login_to_device.size();
        }
        res.set_content(j.dump(2), "application/json");
    });

    svr.Get("/api/devices", [](const httplib::Request&, httplib::Response& res) {
        json j = json::array();
        {
            std::lock_guard<std::mutex> lock(g_device_mutex);
            for (const auto& pair : g_devices) {
                const auto& d = pair.second;
                json item;
                item["device_id"] = d.device_id;
                item["login_id"] = d.login_id;
                item["ip"] = d.ip;
                item["serial"] = d.serial;
                item["protocol_version"] = d.protocol_ver;
                item["online"] = d.online;
                item["last_seen"] = d.last_seen;
                j.push_back(item);
            }
        }
        res.set_content(j.dump(2), "application/json");
    });

    svr.Post("/api/set-key", [](const httplib::Request& req, httplib::Response& res) {
        try {
            auto j = json::parse(req.body);
            std::string device_id = j.value("device_id", "");
            std::string key = j.value("encryption_key", "");
            if (device_id.empty() || key.empty()) {
                res.status = 400;
                res.set_content("{\"error\": \"device_id and encryption_key required\"}", "application/json");
                return;
            }
            {
                std::lock_guard<std::mutex> lock(g_device_mutex);
                g_device_keys[device_id] = key;
            }
            res.set_content("{\"success\": true}", "application/json");
        } catch (const std::exception& e) {
            res.status = 400;
            res.set_content(std::string("{\"error\": \"") + e.what() + "\"}", "application/json");
        }
    });

    svr.Post("/api/isapi", [](const httplib::Request& req, httplib::Response& res) {
        try {
            auto j = json::parse(req.body);
            std::string device_id = j.value("device_id", "");
            std::string method = j.value("method", "POST");
            std::string url = j.value("url", "");
            std::string body = j.value("body", "");

            LONG login_id = -1;
            {
                std::lock_guard<std::mutex> lock(g_device_mutex);
                auto it = g_devices.find(device_id);
                if (it != g_devices.end() && it->second.online) {
                    login_id = it->second.login_id;
                }
            }

            if (login_id < 0) {
                res.status = 404;
                res.set_content("{\"error\": \"Device not online or not registered via ISUP\"}", "application/json");
                return;
            }

            std::vector<char> outBuffer(262144, 0);
            NET_EHOME_PTXML_PARAM param = {0};
            param.pRequestUrl = (void*)url.c_str();
            param.dwRequestUrlLen = (DWORD)url.length();
            if (!body.empty()) {
                param.pInBuffer = (void*)body.c_str();
                param.dwInSize = (DWORD)body.length();
            }
            param.pOutBuffer = outBuffer.data();
            param.dwOutSize = (DWORD)outBuffer.size();
            param.dwRecvTimeOut = 10000;

            BOOL ok = FALSE;
            if (method == "GET") {
                ok = NET_ECMS_GetPTXMLConfig(login_id, &param);
            } else if (method == "PUT") {
                ok = NET_ECMS_PutPTXMLConfig(login_id, &param);
            } else if (method == "DELETE") {
                ok = NET_ECMS_DeletePTXMLConfig(login_id, &param);
            } else {
                ok = NET_ECMS_PostPTXMLConfig(login_id, &param);
            }

            if (!ok) {
                DWORD err = NET_ECMS_GetLastError();
                res.status = 500;
                json err_j = {{"success", false}, {"error_code", err}};
                res.set_content(err_j.dump(), "application/json");
                return;
            }

            std::string resp_str(outBuffer.data(), param.dwReturnedXMLLen > 0 ? param.dwReturnedXMLLen : strlen(outBuffer.data()));
            json success_j = {{"success", true}, {"response", resp_str}};
            res.set_content(success_j.dump(), "application/json");

        } catch (const std::exception& e) {
            res.status = 400;
            res.set_content(std::string("{\"error\": \"") + e.what() + "\"}", "application/json");
        }
    });

    std::cout << "✔ HTTP REST API listening on 0.0.0.0:" << g_config.api_port << std::endl;
    svr.listen("0.0.0.0", g_config.api_port);

    if (alarmHandle >= 0) {
        NET_EALARM_StopListen(alarmHandle);
        NET_EALARM_Fini();
    }
    NET_ECMS_StopListen(listenHandle);
    NET_ECMS_Fini();
    return 0;
}
