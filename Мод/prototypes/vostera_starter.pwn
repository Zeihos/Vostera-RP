// VOSTERA ROLEPLAY | independently written gameplay prototype.
// This is NOT the original v0.10 game mode or a production server.
// Deliberately no passwords, accounts, database, shop or admin permissions.

#include <open.mp>

#define VOSTERA_COLOR_WHITE 0xFFFFFFFF
#define VOSTERA_COLOR_INFO  0xCBA6EFFF
#define VOSTERA_COLOR_RP    0xE6DDEEFF
#define VOSTERA_COLOR_ERROR 0xFF697FFF

#define VOSTERA_JOB_NONE     0
#define VOSTERA_JOB_COURIER  1
#define VOSTERA_JOB_MECHANIC 2
#define VOSTERA_WORK_COOLDOWN 90
#define VOSTERA_RP_DISTANCE 30.0

new g_PlayerJob[MAX_PLAYERS];
new g_LastWork[MAX_PLAYERS];
new g_WorkCount[MAX_PLAYERS];

main()
{
    print("VOSTERA ROLEPLAY | original development prototype");
    print("No persistent accounts or production systems are enabled.");
}

public OnGameModeInit()
{
    SetGameModeText("VOSTERA | Prototype");
    AddPlayerClass(26, 2495.3547, -1688.2319, 13.6774, 351.1646);
    AddStaticVehicle(466, 2493.7583, -1683.6482, 12.9099, 270.8069, -1, -1);
    DisableInteriorEnterExits();
    return 1;
}

public OnPlayerConnect(playerid)
{
    g_PlayerJob[playerid] = VOSTERA_JOB_NONE;
    g_LastWork[playerid] = 0;
    g_WorkCount[playerid] = 0;
    SetPlayerScore(playerid, 0);
    SendClientMessage(playerid, VOSTERA_COLOR_INFO,
        "VOSTERA | Development prototype. /help - commands; no progress is saved.");
    return 1;
}

public OnPlayerDisconnect(playerid, reason)
{
    // Player IDs are reused; never retain a previous player's session state.
    g_PlayerJob[playerid] = VOSTERA_JOB_NONE;
    g_LastWork[playerid] = 0;
    g_WorkCount[playerid] = 0;
    return 1;
}

public OnPlayerSpawn(playerid)
{
    SetPlayerColor(playerid, VOSTERA_COLOR_WHITE);
    SendClientMessage(playerid, VOSTERA_COLOR_INFO,
        "VOSTERA | Use /job courier or /job mechanic to test a temporary job.");
    return 1;
}

stock VosteraNearbyMessage(playerid, const message[])
{
    new Float:x, Float:y, Float:z;
    GetPlayerPos(playerid, x, y, z);
    new world = GetPlayerVirtualWorld(playerid);
    new interior = GetPlayerInterior(playerid);
    for (new target = 0; target < MAX_PLAYERS; target++)
    {
        if (!IsPlayerConnected(target)) continue;
        if (GetPlayerVirtualWorld(target) != world) continue;
        if (GetPlayerInterior(target) != interior) continue;
        if (GetPlayerDistanceFromPoint(target, x, y, z) > VOSTERA_RP_DISTANCE) continue;
        SendClientMessage(target, VOSTERA_COLOR_RP, message);
    }
    return 1;
}

public OnPlayerText(playerid, text[])
{
    new name[MAX_PLAYER_NAME + 1];
    new message[180];
    GetPlayerName(playerid, name, sizeof name);
    format(message, sizeof message, "%s says: %s", name, text);
    VosteraNearbyMessage(playerid, message);
    // Prevent standard global chat: speech is local to the same world/interior.
    return 0;
}

public OnPlayerCommandText(playerid, cmdtext[])
{
    if (!strcmp(cmdtext, "/help", true))
    {
        SendClientMessage(playerid, VOSTERA_COLOR_INFO, "VOSTERA | Available prototype commands:");
        SendClientMessage(playerid, VOSTERA_COLOR_WHITE, "/stats, /rules, /me <action>, /do <scene>");
        SendClientMessage(playerid, VOSTERA_COLOR_WHITE, "/job courier, /job mechanic, /job none, /work");
        return 1;
    }
    if (!strcmp(cmdtext, "/rules", true))
    {
        SendClientMessage(playerid, VOSTERA_COLOR_INFO, "Respect other players. No exploits or chat spam.");
        SendClientMessage(playerid, VOSTERA_COLOR_INFO, "This test server does not store accounts or progress.");
        return 1;
    }
    if (!strcmp(cmdtext, "/stats", true))
    {
        new jobName[18];
        new output[140];
        if (g_PlayerJob[playerid] == VOSTERA_JOB_COURIER) jobName = "Courier";
        else if (g_PlayerJob[playerid] == VOSTERA_JOB_MECHANIC) jobName = "Mechanic";
        else jobName = "Unemployed";
        format(output, sizeof output, "VOSTERA | Job: %s | Completed: %d | All stats reset on reconnect.",
            jobName, g_WorkCount[playerid]);
        SendClientMessage(playerid, VOSTERA_COLOR_WHITE, output);
        return 1;
    }
    if (!strcmp(cmdtext, "/job courier", true))
    {
        g_PlayerJob[playerid] = VOSTERA_JOB_COURIER;
        SendClientMessage(playerid, VOSTERA_COLOR_INFO, "You are now a courier. /work to test a task.");
        return 1;
    }
    if (!strcmp(cmdtext, "/job mechanic", true))
    {
        g_PlayerJob[playerid] = VOSTERA_JOB_MECHANIC;
        SendClientMessage(playerid, VOSTERA_COLOR_INFO, "You are now a mechanic. /work to test a task.");
        return 1;
    }
    if (!strcmp(cmdtext, "/job none", true))
    {
        g_PlayerJob[playerid] = VOSTERA_JOB_NONE;
        SendClientMessage(playerid, VOSTERA_COLOR_INFO, "You left your temporary job.");
        return 1;
    }
    if (!strcmp(cmdtext, "/work", true))
    {
        if (g_PlayerJob[playerid] == VOSTERA_JOB_NONE)
        {
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR, "Choose a job first: /job courier or /job mechanic.");
            return 1;
        }
        new now = gettime();
        if (g_LastWork[playerid] != 0 && now - g_LastWork[playerid] < VOSTERA_WORK_COOLDOWN)
        {
            new output[100];
            format(output, sizeof output, "Wait %d seconds before the next temporary task.",
                VOSTERA_WORK_COOLDOWN - (now - g_LastWork[playerid]));
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR, output);
            return 1;
        }
        g_LastWork[playerid] = now;
        g_WorkCount[playerid]++;
        GivePlayerMoney(playerid, 125);
        SetPlayerScore(playerid, g_WorkCount[playerid]);
        SendClientMessage(playerid, VOSTERA_COLOR_INFO,
            "Test task completed: $125 (session-only). No tasks/payouts are persisted.");
        return 1;
    }
    if (strfind(cmdtext, "/me ", true) == 0 && strlen(cmdtext) > 4)
    {
        new name[MAX_PLAYER_NAME + 1];
        new output[180];
        GetPlayerName(playerid, name, sizeof name);
        new action[130];
        strmid(action, cmdtext, 4, strlen(cmdtext), sizeof action);
        format(output, sizeof output, "* %s %s", name, action);
        VosteraNearbyMessage(playerid, output);
        return 1;
    }
    if (strfind(cmdtext, "/do ", true) == 0 && strlen(cmdtext) > 4)
    {
        new name[MAX_PLAYER_NAME + 1];
        new output[180];
        GetPlayerName(playerid, name, sizeof name);
        new scene[130];
        strmid(scene, cmdtext, 4, strlen(cmdtext), sizeof scene);
        format(output, sizeof output, "* %s (( %s ))", scene, name);
        VosteraNearbyMessage(playerid, output);
        return 1;
    }
    SendClientMessage(playerid, VOSTERA_COLOR_ERROR, "Unknown command. Use /help.");
    return 1;
}
