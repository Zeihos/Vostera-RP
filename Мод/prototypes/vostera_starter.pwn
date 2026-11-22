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
#define VOSTERA_COURIER_REWARD 125
#define VOSTERA_MECHANIC_REWARD 100
#define VOSTERA_DELIVERY_COUNT 3
#define VOSTERA_DELIVERY_TIMEOUT 600
#define VOSTERA_DEPOT_DISTANCE 20.0
#define VOSTERA_DEPOT_X 2495.3547
#define VOSTERA_DEPOT_Y -1688.2319
#define VOSTERA_DEPOT_Z 13.6774

// Courier destinations in Los Santos. Only one player checkpoint is active.
new Float:g_CourierStops[VOSTERA_DELIVERY_COUNT][3] =
{
    {2115.5767, -1773.9658, 13.3895},
    {1836.1743, -1682.6177, 13.3472},
    {2366.1179, -1888.3264, 13.3828}
};

// Temporary mechanic workbench; near the starting vehicle.
#define VOSTERA_SERVICE_X 2493.7583
#define VOSTERA_SERVICE_Y -1683.6482
#define VOSTERA_SERVICE_Z 12.9099


new g_PlayerJob[MAX_PLAYERS];
new g_LastWork[MAX_PLAYERS];
new g_WorkCount[MAX_PLAYERS];
new bool:g_CourierActive[MAX_PLAYERS];
new g_CourierDestination[MAX_PLAYERS];
new g_DeliveryStart[MAX_PLAYERS];

// Pure predicates: verified by the real open.mp startup smoke test.
stock bool:VosteraDeliveryExpired(startTime, currentTime)
{
    return startTime <= 0 || currentTime < startTime ||
        currentTime - startTime >= VOSTERA_DELIVERY_TIMEOUT;
}

stock bool:VosteraWorkCooldown(lastWork, currentTime)
{
    return lastWork != 0 &&
        (currentTime < lastWork || currentTime - lastWork < VOSTERA_WORK_COOLDOWN);
}

stock VosteraPrototypeSelfTest()
{
    new passed = 0;
    if (VosteraDeliveryExpired(0, 100)) passed++;
    if (VosteraDeliveryExpired(100, 99)) passed++;
    if (!VosteraDeliveryExpired(100, 699)) passed++;
    if (VosteraDeliveryExpired(100, 700)) passed++;
    if (!VosteraWorkCooldown(0, 100)) passed++;
    if (VosteraWorkCooldown(100, 189)) passed++;
    if (!VosteraWorkCooldown(100, 190)) passed++;
    if (passed == 7) print("VOSTERA-SELFTEST:PASS 7/7");
    else print("VOSTERA-SELFTEST:FAIL");
    return passed;
}


stock VosteraCancelDelivery(playerid)
{
    if (g_CourierActive[playerid]) DisablePlayerCheckpoint(playerid);
    g_CourierActive[playerid] = false;
    g_CourierDestination[playerid] = -1;
    g_DeliveryStart[playerid] = 0;
    return 1;
}

stock VosteraCompleteWork(playerid, reward)
{
    g_LastWork[playerid] = gettime();
    g_WorkCount[playerid]++;
    GivePlayerMoney(playerid, reward);
    SetPlayerScore(playerid, g_WorkCount[playerid]);
    return 1;
}

stock bool:VosteraCanWorkInWorld(playerid)
{
    return GetPlayerInterior(playerid) == 0 && GetPlayerVirtualWorld(playerid) == 0;
}


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
    VosteraPrototypeSelfTest();
    return 1;
}

public OnPlayerConnect(playerid)
{
    g_PlayerJob[playerid] = VOSTERA_JOB_NONE;
    g_LastWork[playerid] = 0;
    g_WorkCount[playerid] = 0;
    // A reused player slot must never inherit a checkpoint or task.
    g_CourierActive[playerid] = false;
    g_CourierDestination[playerid] = -1;
    g_DeliveryStart[playerid] = 0;
    SetPlayerScore(playerid, 0);
    SendClientMessage(playerid, VOSTERA_COLOR_INFO,
        "VOSTERA | Development prototype. /help - commands; no progress is saved.");
    return 1;
}

public OnPlayerDisconnect(playerid, reason)
{
    // Player IDs are reused; never retain a previous player's session state.
    VosteraCancelDelivery(playerid);
    g_PlayerJob[playerid] = VOSTERA_JOB_NONE;
    g_LastWork[playerid] = 0;
    g_WorkCount[playerid] = 0;
    return 1;
}

public OnPlayerSpawn(playerid)
{
    // Death cancels an unfinished delivery; never reward on a stale checkpoint.
    VosteraCancelDelivery(playerid);
    SetPlayerColor(playerid, VOSTERA_COLOR_WHITE);
    SendClientMessage(playerid, VOSTERA_COLOR_INFO,
        "VOSTERA | Use /job courier or /job mechanic to test a temporary job.");
    return 1;
}

public OnPlayerEnterCheckpoint(playerid)
{
    if (!g_CourierActive[playerid]) return 1;
    if (g_PlayerJob[playerid] != VOSTERA_JOB_COURIER || !VosteraCanWorkInWorld(playerid))
    {
        VosteraCancelDelivery(playerid);
        return 1;
    }
    if (VosteraDeliveryExpired(g_DeliveryStart[playerid], gettime()))
    {
        VosteraCancelDelivery(playerid);
        SendClientMessage(playerid, VOSTERA_COLOR_ERROR,
            "Delivery expired after 10 minutes. Return to depot and start a new route.");
        return 1;
    }
    new destination = g_CourierDestination[playerid];
    if (destination < 0 || destination >= VOSTERA_DELIVERY_COUNT)
    {
        VosteraCancelDelivery(playerid);
        return 1;
    }
    // Verify the actual position, not only the callback invocation.
    if (GetPlayerDistanceFromPoint(playerid,
        g_CourierStops[destination][0],
        g_CourierStops[destination][1],
        g_CourierStops[destination][2]) > 7.0) return 1;

    VosteraCancelDelivery(playerid);
    VosteraCompleteWork(playerid, VOSTERA_COURIER_REWARD);
    SendClientMessage(playerid, VOSTERA_COLOR_INFO,
        "Courier delivery completed: $125. This payout is not saved.");
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
        SendClientMessage(playerid, VOSTERA_COLOR_WHITE, "/job courier, /job mechanic, /job none, /work, /workstatus, /cancelwork");
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
        VosteraCancelDelivery(playerid);
        g_PlayerJob[playerid] = VOSTERA_JOB_COURIER;
        SendClientMessage(playerid, VOSTERA_COLOR_INFO, "You are now a courier. /work to test a task.");
        return 1;
    }
    if (!strcmp(cmdtext, "/job mechanic", true))
    {
        VosteraCancelDelivery(playerid);
        g_PlayerJob[playerid] = VOSTERA_JOB_MECHANIC;
        SendClientMessage(playerid, VOSTERA_COLOR_INFO, "You are now a mechanic. /work to test a task.");
        return 1;
    }
    if (!strcmp(cmdtext, "/job none", true))
    {
        VosteraCancelDelivery(playerid);
        g_PlayerJob[playerid] = VOSTERA_JOB_NONE;
        SendClientMessage(playerid, VOSTERA_COLOR_INFO, "You left your temporary job.");
        return 1;
    }
    if (!strcmp(cmdtext, "/cancelwork", true))
    {
        if (!g_CourierActive[playerid])
        {
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR, "No delivery is active.");
            return 1;
        }
        VosteraCancelDelivery(playerid);
        SendClientMessage(playerid, VOSTERA_COLOR_INFO, "Delivery cancelled. No payment awarded.");
        return 1;
    }
    if (!strcmp(cmdtext, "/workstatus", true))
    {
        new status[150];
        new currentTime = gettime();
        if (g_CourierActive[playerid])
        {
            if (VosteraDeliveryExpired(g_DeliveryStart[playerid], currentTime))
            {
                VosteraCancelDelivery(playerid);
                SendClientMessage(playerid, VOSTERA_COLOR_ERROR,
                    "Delivery expired. Return to the depot for a new route.");
                return 1;
            }
            format(status, sizeof status,
                "Courier: drop-off #%d/3, %d sec remaining. /cancelwork to abort.",
                g_CourierDestination[playerid] + 1,
                VOSTERA_DELIVERY_TIMEOUT - (currentTime - g_DeliveryStart[playerid]));
        }
        else if (VosteraWorkCooldown(g_LastWork[playerid], currentTime))
        {
            format(status, sizeof status, "Next job in %d seconds.",
                VOSTERA_WORK_COOLDOWN - (currentTime - g_LastWork[playerid]));
        }
        else if (g_PlayerJob[playerid] == VOSTERA_JOB_COURIER)
        {
            format(status, sizeof status, "Courier: return to the depot at spawn and use /work.");
        }
        else if (g_PlayerJob[playerid] == VOSTERA_JOB_MECHANIC)
        {
            format(status, sizeof status, "Mechanic: take a damaged vehicle to the spawn workshop.");
        }
        else
        {
            format(status, sizeof status, "Choose a job with /job courier or /job mechanic.");
        }
        SendClientMessage(playerid, VOSTERA_COLOR_INFO, status);
        return 1;
    }
    if (!strcmp(cmdtext, "/work", true))
    {
        if (g_PlayerJob[playerid] == VOSTERA_JOB_NONE)
        {
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR, "Choose a job first: /job courier or /job mechanic.");
            return 1;
        }
        if (!VosteraCanWorkInWorld(playerid))
        {
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR, "Work is available only in the main world/interior.");
            return 1;
        }
        if (g_CourierActive[playerid])
        {
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR, "Finish or cancel your current delivery first.");
            return 1;
        }
        new now = gettime();
        if (VosteraWorkCooldown(g_LastWork[playerid], now))
        {
            new output[100];
            format(output, sizeof output, "Wait %d seconds before the next task.",
                VOSTERA_WORK_COOLDOWN - (now - g_LastWork[playerid]));
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR, output);
            return 1;
        }
        if (g_PlayerJob[playerid] == VOSTERA_JOB_COURIER)
        {
            if (GetPlayerDistanceFromPoint(playerid,
                VOSTERA_DEPOT_X, VOSTERA_DEPOT_Y, VOSTERA_DEPOT_Z) > VOSTERA_DEPOT_DISTANCE)
            {
                SendClientMessage(playerid, VOSTERA_COLOR_ERROR,
                    "Courier routes start at the depot near the Los Santos spawn.");
                return 1;
            }
            new destination = g_WorkCount[playerid] % VOSTERA_DELIVERY_COUNT;
            if (!SetPlayerCheckpoint(playerid,
                g_CourierStops[destination][0],
                g_CourierStops[destination][1],
                g_CourierStops[destination][2], 6.0))
            {
                SendClientMessage(playerid, VOSTERA_COLOR_ERROR, "Failed to start the delivery.");
                return 1;
            }
            g_CourierDestination[playerid] = destination;
            g_DeliveryStart[playerid] = now;
            g_CourierActive[playerid] = true;
            SendClientMessage(playerid, VOSTERA_COLOR_INFO,
                "Courier route started at depot. Deliver within 10 min for $125.");
            return 1;
        }
        if (GetPlayerState(playerid) != PLAYER_STATE_DRIVER)
        {
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR,
                "Mechanic: drive a damaged vehicle to the workshop near the spawn.");
            return 1;
        }
        if (GetPlayerDistanceFromPoint(playerid,
            VOSTERA_SERVICE_X, VOSTERA_SERVICE_Y, VOSTERA_SERVICE_Z) > 15.0)
        {
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR,
                "Mechanic: visit the workshop next to the starting car.");
            return 1;
        }
        new vehicleid = GetPlayerVehicleID(playerid);
        if (vehicleid <= 0)
        {
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR, "No vehicle to repair.");
            return 1;
        }
        new Float:health;
        if (!GetVehicleHealth(vehicleid, health) || health >= 950.0)
        {
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR,
                "The vehicle does not need repairs (health must be below 950).");
            return 1;
        }
        if (!RepairVehicle(vehicleid))
        {
            SendClientMessage(playerid, VOSTERA_COLOR_ERROR, "Vehicle repair failed.");
            return 1;
        }
        VosteraCompleteWork(playerid, VOSTERA_MECHANIC_REWARD);
        SendClientMessage(playerid, VOSTERA_COLOR_INFO,
            "Mechanic task completed: vehicle repaired, $100 (session-only).");
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
